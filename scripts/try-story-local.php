<?php

// Synthetic comparison using the actual two-stage service; never a video API.
require dirname(__DIR__).'/vendor/autoload.php';

$model = $argv[1] ?? 'qwen3:8b';
$suite = in_array('--suite', $argv, true);
$http = Symfony\Component\HttpClient\HttpClient::create();
$writer = new App\Service\LocalStoryWriter($http, true, 'http://host.docker.internal:11434', $model);
$allCases = require __DIR__.'/story-local-cases.php';
$cases = $suite ? $allCases : [$allCases[0]];
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--case=')) {
        $cases = array_values(array_filter($allCases, static fn (array $case): bool => $case['id'] === substr($argument, 7)));
        if (!$cases) {
            throw new \InvalidArgumentException('Sujet synthétique inconnu.');
        }
    }
}
if (in_array('--repeat', $argv, true)) {
    foreach (array_slice($cases, 0, 2) as $case) {
        $case['id'] .= '-repeat';
        $cases[] = $case;
    }
}
if (in_array('--warm', $argv, true)) {
    $warm = $http->request('POST', 'http://host.docker.internal:11434/api/generate', ['max_redirects' => 0, 'timeout' => 180, 'max_duration' => 180, 'json' => ['model' => $model, 'stream' => false, 'keep_alive' => '2m', 'options' => ['num_ctx' => 4096]]])->toArray();
    if (true !== ($warm['done'] ?? null)) {
        throw new \RuntimeException('Préchargement du modèle incomplet.');
    }
}
$directory = dirname(__DIR__).'/artifacts/story-local/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
$save = $suite || count($cases) > 1;
if ($save && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new \RuntimeException('Impossible de créer le dossier des résultats synthétiques.');
}
$failed = false;
foreach ($cases as $case) {
    $start = microtime(true);
    $monotonicStart = hrtime(true);
    try {
        $result = $writer->propose($case['idea'], $case['durations'], $case['continuity'], $case['tone'], $case['context']);
        $row = ['case' => $case['id'], 'model' => $model, 'seconds' => round(microtime(true) - $start, 3), 'input' => $case, 'result' => $result];
    } catch (\RuntimeException $error) {
        $failed = true;
        $row = ['case' => $case['id'], 'model' => $model, 'seconds' => round(microtime(true) - $start, 3), 'input' => $case, 'error' => $error->getMessage()];
    }
    $row['clock_gap_seconds'] = round(max(0, microtime(true) - $start - (hrtime(true) - $monotonicStart) / 1000000000), 3);
    $json = json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    if ($save) {
        if (false === file_put_contents($directory.'/'.$case['id'].'.json', $json)) {
            throw new \RuntimeException('Impossible de conserver le résultat synthétique.');
        }
        echo json_encode(['case' => $case['id'], 'seconds' => $row['seconds'], 'success' => !isset($row['error']), 'warnings' => count($row['result']['warnings'] ?? []), 'clock_gap_seconds' => $row['clock_gap_seconds'], 'file' => $directory.'/'.$case['id'].'.json'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    } else {
        echo $json;
    }
}
exit($failed ? 1 : 0);
