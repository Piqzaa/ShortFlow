<?php

// Local review only: php -S 127.0.0.1:8027 -t public scripts/product-demo-router.php
// Replays actual screens captured after import/save, never forwards a form.
if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    http_response_code(405);
    exit('Read-only demonstration.');
}
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
if (preg_match('~^/(build|media|fonts)/~', $path) || in_array($path, ['/favicon.svg', '/cursor.svg', '/cursor-active.svg'], true)) {
    $public = realpath($root.'/public');
    $file = realpath($public.$path);
    if (false !== $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
        return false;
    }
}
$manifest = $root.'/artifacts/r62/demo.json';
if (!is_file($manifest)) {
    http_response_code(503);
    exit('Run scripts/prepare-product-demo.php in the test environment first.');
}
$data = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
if ('/studio/bibliotheque/'.$data['video']['id'].'/lecture' === $path) {
    header('Content-Type: video/webm');
    readfile($root.'/artifacts/r62/film.webm');
    exit;
}
if ('/demo' === $path || '/' === $path) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Démonstration locale VydFlow</title><link rel="icon" href="/favicon.svg" type="image/svg+xml"><style>body{margin:0;background:#0c0d12;color:#eee;font:14px system-ui}header{padding:16px 24px}p{max-width:100ch;line-height:1.6}nav{display:flex;gap:16px;flex-wrap:wrap}a{color:#c6a8ff}iframe{width:100%;height:78vh;border:0}</style><header><h1>Du film au brouillon</h1><p>Import et enregistrement réellement exécutés en local avec le film du projet. Relecture de quatre écrans capturés : les formulaires ne sont pas reliés à l’application. Le brouillon est incomplet, sans compte connecté, sans génération IA et sans envoi. Les données de recette ont été annulées.</p><nav><a href="/demo/upload" target="demo">1. Importer</a><a href="/demo/imported" target="demo">2. Vidéo importée</a><a href="/demo/prepare" target="demo">3. Préparer</a><a href="/demo/saved" target="demo">4. Brouillon enregistré</a></nav></header><iframe name="demo" title="Écrans issus de la démonstration locale" src="/demo/upload"></iframe></html>';
    exit;
}
if (preg_match('~^/demo/(upload|imported|prepare|saved)$~D', $path, $match)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($root.'/artifacts/r62/'.$match[1].'.html');
    exit;
}
http_response_code(404);
echo 'No demo screen at this path.';
