<?php

declare(strict_types=1);

// A restricted, temporary entry point. Never point the tunnel at the dev server.
if ('0' !== getenv('APP_DEBUG') || 'dev' !== getenv('APP_ENV')) {
    http_response_code(503);
    exit;
}

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path) || preg_match('/%(?:2e|2f|5c|00)/i', $path) || str_contains($path, '\\') || preg_match('#(?:^|/)\.#', $path)) {
    http_response_code(404);
    exit;
}

// This service is reached through the HTTPS-only tunnel, with no public Docker port.
// Do not trust client-provided Forwarded/X-Forwarded-* headers.
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';

if ('/' === $path) {
    header('Location: /connexion', true, 302);
    exit;
}

// Only the exact TikTok ownership proof supplied by the app owner is public.
if ('/tiktokcdhRfEm4ejiwaGoxZqVcLdkkVfxcSJQ7.txt' === $path && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    $proof = __DIR__.'/../../public/tiktokcdhRfEm4ejiwaGoxZqVcLdkkVfxcSJQ7.txt';
    if (!is_file($proof)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    if ('GET' === $_SERVER['REQUEST_METHOD']) {
        readfile($proof);
    }
    exit;
}

// Static files are served explicitly: never let the CLI server bypass this guard.
if (str_starts_with($path, '/build/') || '/media/creators/hero-fashion.webp' === $path) {
    $public = realpath(__DIR__.'/../../public'.(str_starts_with($path, '/build/') ? '/build' : '/media/creators'));
    $file = realpath(__DIR__.'/../../public'.$path);
    $types = ['css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
    if (!$public || !$file || !str_starts_with($file, $public.DIRECTORY_SEPARATOR) || !is_file($file) || !isset($types[$extension]) || !in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: '.$types[$extension]);
    if ('GET' === $_SERVER['REQUEST_METHOD']) {
        readfile($file);
    }
    exit;
}

$allowed = in_array($path, ['/connexion', '/deconnexion', '/studio', '/cgu', '/confidentialite'], true)
    || str_starts_with($path, '/studio/')
    || (bool) preg_match('#^/media/(instagram|tiktok)/\d+/\d+/[a-f0-9]{64}$#D', $path);
if (!$allowed) {
    http_response_code(404);
    exit;
}

// Symfony Runtime reloads SCRIPT_FILENAME to obtain its application factory.
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__.'/index.php');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require $_SERVER['SCRIPT_FILENAME'];
