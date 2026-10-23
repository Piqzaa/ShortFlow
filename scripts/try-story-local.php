<?php

// Synthetic comparison using the actual two-stage service; never a video API.
require dirname(__DIR__).'/vendor/autoload.php';

$model = $argv[1] ?? 'qwen3:4b';
$writer = new App\Service\LocalStoryWriter(Symfony\Component\HttpClient\HttpClient::create(), true, 'http://host.docker.internal:11434', $model);
$start = microtime(true);
try {
    $result = $writer->propose('Milo mon chat blanc joueur découvre les objets du quotidien.', [10, 15, 15], 'independent', 'auto');
    echo json_encode(['model' => $model, 'seconds' => round(microtime(true) - $start, 3), 'result' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
} catch (\RuntimeException $error) {
    echo json_encode(['model' => $model, 'seconds' => round(microtime(true) - $start, 3), 'error' => $error->getMessage()], JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
