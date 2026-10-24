<?php

// Reapply deterministic diagnostics to saved synthetic proposals. No inference or database.
require dirname(__DIR__).'/vendor/autoload.php';
$root = realpath(dirname(__DIR__).'/artifacts/story-local');
$directory = realpath($argv[1] ?? '');
if (!$root || !$directory || !is_dir($directory) || !str_starts_with($directory.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)) {
    throw new InvalidArgumentException('Choisis un dossier de résultats dans artifacts/story-local.');
}
$writer = new App\Service\LocalStoryWriter(Symfony\Component\HttpClient\HttpClient::create(), false, 'http://localhost:11434', 'unused');
$report = ['reviewer_sha256' => hash_file('sha256', dirname(__DIR__).'/src/Service/LocalStoryWriter.php'), 'cases' => []];
foreach (glob($directory.'/*.json') as $file) {
    $row = json_decode(file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
    if (!isset($row['input'], $row['result'])) {
        continue;
    }
    $report['cases'][] = ['case' => $row['case'], 'warnings' => $writer->review($row['input']['idea'], $row['input']['context'], $row['result'])];
}
if (false === file_put_contents($directory.'/review.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n")) {
    throw new RuntimeException('Impossible de conserver le bilan des diagnostics.');
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
