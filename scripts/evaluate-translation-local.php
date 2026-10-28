<?php

// Fixed synthetic inputs, local text endpoint only. No Kernel, database, video or publishing.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Service\LocalStoryWriter;
use App\Service\StoryTextChecks;
use App\Service\StoryWritingException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$corpusPath = dirname(__DIR__).'/docs/fixtures/redacteur-evaluation-r2.json';
$corpus = json_decode(file_get_contents($corpusPath), true, 32, JSON_THROW_ON_ERROR);
$cases = array_values(array_filter($corpus['cases'], static fn (array $case): bool => in_array($case['id'], ['recette', 'dialogue'], true)));
$cases[] = ['id' => 'banane-fraise-synthetique', 'kind' => 'story', 'input' => ['idea' => 'Une banane jaune et une fraise rouge anthropomorphes attendent devant une porte. La banane ouvre la porte, entre puis ressort et salue la fraise. Sans dialogue.', 'durations' => [10, 10, 10], 'continuity' => 'independent', 'tone' => 'humour', 'context' => []]];
if (in_array('--dry-run', $argv, true)) {
    echo json_encode(['cases' => array_column($cases, 'id'), 'repeats' => 2, 'model' => 'qwen3:8b', 'corpusSha256' => hash_file('sha256', $corpusPath)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
$root = dirname(__DIR__).'/artifacts/translation-r2/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
if (!mkdir($root, 0775, true)) {
    throw new RuntimeException('Impossible de conserver la campagne.');
}
$save = static function (string $path, array $data): void {
    if (false === file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL)) {
        throw new RuntimeException('Échec de conservation.');
    }
};
$real = HttpClient::create();
$metadata = ['startedAt' => gmdate(DATE_ATOM), 'model' => 'qwen3:8b', 'corpusSha256' => hash_file('sha256', $corpusPath), 'runnerSha256' => hash_file('sha256', __FILE__), 'sources' => []];
foreach (['LocalStoryWriter', 'StoryTextChecks'] as $source) {
    $metadata['sources'][$source] = hash_file('sha256', dirname(__DIR__).'/src/Service/'.$source.'.php');
}
$metadata['ollama'] = $real->request('GET', 'http://host.docker.internal:11434/api/version', ['timeout' => 5, 'max_duration' => 5, 'max_redirects' => 0])->toArray();
$save($root.'/metadata.json', $metadata);
echo 'Captures: '.$root.PHP_EOL;
$failed = false;
for ($repeat = 1; $repeat <= 2; ++$repeat) {
    foreach ($cases as $case) {
        $calls = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use ($real, &$calls): MockResponse {
            if ('POST' !== $method || 'http://host.docker.internal:11434/api/chat' !== $url) {
                throw new LogicException('Seul le moteur texte local est autorisé.');
            }
            $index = count($calls);
            $calls[$index] = ['request' => json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR)];
            $start = hrtime(true);
            try {
                $response = $real->request($method, $url, $options);
                $body = $response->getContent(false);
                $calls[$index]['status'] = $response->getStatusCode();
                $calls[$index]['body'] = $body;

                return new MockResponse($body, ['http_code' => $response->getStatusCode()]);
            } catch (Throwable $error) {
                $calls[$index]['errorClass'] = get_class($error);
                throw $error;
            } finally {
                $calls[$index]['seconds'] = round((hrtime(true) - $start) / 1e9, 6);
            }
        });
        $input = $case['input'];
        $row = ['id' => $case['id'], 'repeat' => $repeat, 'input' => $input, 'startedAt' => gmdate(DATE_ATOM)];
        echo 'Début '.$case['id'].' '.$repeat.PHP_EOL;
        $start = hrtime(true);
        try {
            $row['result'] = (new LocalStoryWriter($http, true, 'http://host.docker.internal:11434', 'qwen3:8b'))->propose($input['idea'], $input['durations'], $input['continuity'], $input['tone'], $input['context']);
            $row['quotesPreserved'] = true;
            foreach ((new StoryTextChecks())->speechLines($input['idea'], $input['context']) as $line) {
                $row['quotesPreserved'] = $row['quotesPreserved'] && str_contains(implode(' ', array_column($row['result']['shots'], 'prompt')), $line);
            }
            $failed = $failed || !$row['result']['translationReady'] || !$row['quotesPreserved'];
        } catch (Throwable $error) {
            $row['error'] = $error instanceof StoryWritingException ? $error->reason : get_class($error);
            $failed = true;
        }
        $row['seconds'] = round((hrtime(true) - $start) / 1e9, 6);
        $row['calls'] = $calls;
        $save($root.'/'.$case['id'].'-'.$repeat.'.json', $row);
        echo json_encode(['id' => $case['id'], 'repeat' => $repeat, 'seconds' => $row['seconds'], 'calls' => count($calls), 'translationReady' => $row['result']['translationReady'] ?? false, 'quotesPreserved' => $row['quotesPreserved'] ?? null, 'error' => $row['error'] ?? null], JSON_THROW_ON_ERROR).PHP_EOL;
    }
}
$metadata['finishedAt'] = gmdate(DATE_ATOM);
$metadata['failed'] = $failed;
$save($root.'/metadata.json', $metadata);
exit($failed ? 1 : 0);
