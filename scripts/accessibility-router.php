<?php

// php -S 127.0.0.1:8021 -t public scripts/accessibility-router.php
// Serve only offline exports and public assets; never forward a form to Symfony.
if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    http_response_code(405);
    exit('Read-only accessibility recipe.');
}
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/(build|media|fonts)/~', $path)) {
    $public = realpath($root.'/public');
    $file = realpath($public.$path);
    if (false !== $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
        return false;
    }
}
if (preg_match('~^/studio/bibliotheque/\d+/lecture$~D', $path)) {
    header('Content-Type: video/mp4');
    readfile($root.'/tests/Fixtures/reels-valid.mp4');
    exit;
}
$manifest = $root.'/artifacts/r53/exports.json';
if (!is_file($manifest)) {
    http_response_code(503);
    exit('Export the test fixtures first.');
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
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$html = file_get_contents($root.'/artifacts/r53/'.$name.'.html');
if (isset($_GET['nojs'])) {
    $html = preg_replace('~<script\b[^>]*>.*?</script>~s', '', $html);
}
echo $html;
