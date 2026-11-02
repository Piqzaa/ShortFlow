<?php

// Offline R5.4 only: php -S 127.0.0.1:8022 -t public scripts/resilience-router.php
if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    http_response_code(405);
    exit('Read-only resilience recipe.');
}
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/(build|media|fonts)/~', $path) || in_array($path, ['/favicon.svg', '/cursor.svg', '/cursor-active.svg'], true)) {
    $public = realpath($root.'/public');
    $file = realpath($public.$path);
    if (false !== $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
        return false;
    }
}
if ('/studio/creation/scenario/dernier' === $path) {
    header('Content-Type: application/json');
    exit('{"status":"none"}');
}
if (preg_match('~^/studio/ia/generations/\d+/etat$~D', $path)) {
    header('Content-Type: application/json');
    if ('error' === ($_GET['qa_poll'] ?? '')) {
        http_response_code(503);
        exit('{"error":"Synthetic status unavailable"}');
    }
    exit('{"status":"processing","updatedAt":"2026-11-02T14:00:00+00:00","stage":"running","checkedAt":"2026-11-02T14:00:00+00:00"}');
}
if (preg_match('~^/studio/bibliotheque/\d+/lecture$~D', $path)) {
    if ('missing' === ($_GET['qa_media'] ?? '')) {
        http_response_code(404);
        exit('Synthetic missing media.');
    }
    if ('slow' === ($_GET['qa_media'] ?? '')) {
        usleep(1800000);
    }
    header('Content-Type: video/mp4');
    readfile($root.'/tests/Fixtures/reels-valid.mp4');
    exit;
}
$fixtureSet = $fixtureSet ?? 'r54';
if (!in_array($fixtureSet, ['r54', 'r55'], true)) {
    throw new RuntimeException('Unknown fixture set.');
}
$manifest = $root.'/artifacts/'.$fixtureSet.'/exports.json';
if (!is_file($manifest)) {
    http_response_code(503);
    exit('Export the R5.4 test fixtures first.');
}
$routes = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR)['routes'];
$name = null;
foreach ($routes as $key => $route) {
    if ($path === '/qa/'.$key || $_SERVER['REQUEST_URI'] === $route['url']) {
        $name = $key;
        break;
    }
}
if (null === $name) {
    http_response_code(404);
    exit('No exported fixture at this path.');
}
$scenario = $_GET['case'] ?? 'normal';
if (!in_array($scenario, ['normal', 'scripts-absent', 'media-missing', 'media-slow', 'poll-error'], true)) {
    http_response_code(400);
    exit('Unknown synthetic scenario.');
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$html = file_get_contents($root.'/artifacts/'.$fixtureSet.'/'.$name.'.html');
if ('scripts-absent' === $scenario) {
    $html = preg_replace('~<script\b[^>]*>.*?</script>~s', '', $html);
}
if (in_array($scenario, ['media-missing', 'media-slow'], true)) {
    $mode = 'media-missing' === $scenario ? 'missing' : 'slow';
    $html = preg_replace('~(/studio/bibliotheque/\d+/lecture)(?=["#])~', '$1?qa_media='.$mode, $html);
    if ('missing' === $mode) {
        $html = preg_replace('~(src=")/media/~', '$1/qa-missing-media/', $html);
    }
}
if ('poll-error' === $scenario) {
    $html = preg_replace('~(data-generation-watch-url-value="[^"]+)"~', '$1?qa_poll=error"', $html);
}
echo $html;
