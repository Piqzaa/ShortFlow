<?php

// Synthetic, local-only evaluation. No Kernel, database, video, account or publishing service.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Service\LocalStoryWriter;
use App\Service\PublicationException;
use App\Service\StoryWritingException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$corpus = json_decode(file_get_contents(dirname(__DIR__).'/docs/fixtures/redacteur-evaluation-r2.json'), true, 32, JSON_THROW_ON_ERROR);
$options = getopt('', ['commit:', 'dry-run', 'case:', 'repeat:']);
$commit = $options['commit'] ?? '';
if (!preg_match('/^[a-f0-9]{40}$/D', $commit)) {
    throw new InvalidArgumentException('Indique --commit=<SHA complet des sources évaluées>.');
}
$cases = $corpus['cases'];
if (isset($options['case'])) {
    $cases = array_values(array_filter($cases, static fn (array $case): bool => $case['id'] === $options['case']));
}
$repeats = isset($options['repeat']) ? [(int) $options['repeat']] : [1, 2];
if (!$cases || array_diff($repeats, [1, 2])) {
    throw new InvalidArgumentException('Cas ou répétition inconnu.');
}
if (isset($options['dry-run'])) {
    echo json_encode(['cases' => array_column($cases, 'id'), 'repeats' => $repeats, 'commit' => $commit, 'model' => 'qwen3:8b'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
$root = dirname(__DIR__).'/artifacts/writer-evaluation-r2/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
if (!mkdir($root, 0775, true)) {
    throw new RuntimeException('Impossible de créer le dossier de résultats.');
}
$save = static function (string $path, array $data): void {
    if (false === file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL)) {
        throw new RuntimeException('Échec de conservation des résultats.');
    }
};
$real = HttpClient::create();
$metadata = ['sourceCommit' => $commit, 'corpusSha256' => hash_file('sha256', dirname(__DIR__).'/docs/fixtures/redacteur-evaluation-r2.json'), 'startedAt' => gmdate(DATE_ATOM), 'model' => 'qwen3:8b', 'php' => PHP_VERSION, 'platform' => PHP_OS_FAMILY, 'hostname' => 'local-workstation', 'repetitions' => $repeats];
$metadata['runnerSha256'] = hash_file('sha256', __FILE__);
foreach (['LocalStoryWriter', 'StoryTextChecks', 'PublicationTexts'] as $sourceFile) {
    $metadata['sourceSha256'][$sourceFile] = hash_file('sha256', dirname(__DIR__).'/src/Service/'.$sourceFile.'.php');
}
foreach (['version', 'ps'] as $endpoint) {
    try {
        $metadata['ollama'][$endpoint] = $real->request('GET', 'http://host.docker.internal:11434/api/'.$endpoint, ['timeout' => 5, 'max_duration' => 5, 'max_redirects' => 0])->toArray();
    } catch (Throwable) {
        $metadata['ollama'][$endpoint] = ['unavailable' => true];
    }
}
$save($root.'/metadata.json', $metadata);
echo json_encode(['directory' => $root, 'startedAt' => $metadata['startedAt']], JSON_THROW_ON_ERROR).PHP_EOL;
$failed = false;
foreach ($repeats as $repeat) {
    foreach ($cases as $case) {
        $calls = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $request) use ($real, &$calls): MockResponse {
            if ('POST' !== $method || 'http://host.docker.internal:11434/api/chat' !== $url) {
                throw new LogicException('Seul le moteur texte local est autorisé.');
            }
            $index = count($calls);
            $wall = microtime(true);
            $mono = hrtime(true);
            $calls[$index] = ['startedAt' => gmdate(DATE_ATOM), 'request' => json_decode($request['body'], true, 32, JSON_THROW_ON_ERROR)];
            try {
                $response = $real->request($method, $url, $request);
                $body = $response->getContent(false);
                $calls[$index]['status'] = $response->getStatusCode();
                $calls[$index]['rawBody'] = $body;
                $calls[$index]['envelope'] = json_decode($body, true);

                return new MockResponse($body, ['http_code' => $response->getStatusCode()]);
            } catch (Throwable $error) {
                $calls[$index]['errorClass'] = get_class($error);
                throw $error;
            } finally {
                $calls[$index]['wallSeconds'] = round(microtime(true) - $wall, 6);
                $calls[$index]['monotonicSeconds'] = round((hrtime(true) - $mono) / 1e9, 6);
            }
        });
        $writer = new LocalStoryWriter($http, true, 'http://host.docker.internal:11434', 'qwen3:8b');
        $wall = microtime(true);
        $mono = hrtime(true);
        $row = ['id' => $case['id'], 'repeat' => $repeat, 'startedAt' => gmdate(DATE_ATOM), 'input' => $case['input'], 'kind' => $case['kind'], 'sourceCommit' => $commit];
        echo json_encode(['starting' => $case['id'], 'repeat' => $repeat], JSON_THROW_ON_ERROR).PHP_EOL;
        try {
            $input = $case['input'];
            $row['result'] = 'story' === $case['kind']
                ? $writer->propose($input['idea'], $input['durations'], $input['continuity'], $input['tone'], $input['context'])
                : $writer->publication($input['source'], $input['profile'], $input['networks']);
        } catch (Throwable $error) {
            $row['error'] = ['class' => get_class($error), 'reason' => $error instanceof PublicationException || $error instanceof StoryWritingException ? $error->reason : 'unexpected', 'message' => $error instanceof PublicationException || $error instanceof StoryWritingException ? $error->getMessage() : 'Échec technique du protocole.'];
            if ('context' !== $row['error']['reason'] || 'import-minimal' !== $case['id']) {
                $failed = true;
            }
        }
        $row['wallSeconds'] = round(microtime(true) - $wall, 6);
        $row['monotonicSeconds'] = round((hrtime(true) - $mono) / 1e9, 6);
        $row['clockGapSeconds'] = round($row['wallSeconds'] - $row['monotonicSeconds'], 6);
        $row['calls'] = $calls;
        $save($root.'/'.$case['id'].'-'.$repeat.'.json', $row);
        echo json_encode(['finished' => $case['id'], 'repeat' => $repeat, 'wallSeconds' => $row['wallSeconds'], 'monotonicSeconds' => $row['monotonicSeconds'], 'calls' => count($calls), 'reason' => $row['error']['reason'] ?? null, 'warnings' => $row['result']['warnings'] ?? []], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    }
}
$metadata['finishedAt'] = gmdate(DATE_ATOM);
$metadata['technicalFailures'] = $failed;
$save($root.'/metadata.json', $metadata);
exit($failed ? 1 : 0);
