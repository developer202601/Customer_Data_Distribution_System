<?php

/**
 * Custom router for `php artisan serve`.
 *
 * The default router lets PHP's built-in web server serve existing files directly,
 * which bypasses Laravel middleware (and its security headers). To keep ZAP
 * results consistent in local scanning, we serve existing public files here and
 * add basic security headers.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$uri = $uri === '' ? '/' : $uri;

// Never serve dotfiles (e.g. /.htaccess, /.env). These can be sensitive and PHP's
// built-in server will happily serve them as static files.
//
// Block:
// - A request for a dotfile at root: "/.htaccess"
// - Any path segment starting with a dot: "/.git/config" or "/foo/.bar"
if ($uri !== '/' && preg_match('#(^|/)\.#', $uri) === 1) {
    http_response_code(404);

    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Type: text/plain; charset=UTF-8');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo "Not Found";
    }
    return;
}

$filePath = __DIR__ . '/public' . $uri;

if (
    $uri !== '/'
    && is_file($filePath)
    && strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)) !== 'php'
) {
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeByExtension = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'map' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'txt' => 'text/plain',
        'html' => 'text/html',
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    $mime = $mimeByExtension[$extension] ?? null;
    if (!is_string($mime) || $mime === '') {
        $mime = function_exists('mime_content_type') ? @mime_content_type($filePath) : null;
    }

    if (!is_string($mime) || $mime === '') {
        $mime = 'application/octet-stream';
    }

    header('Content-Type: ' . $mime);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        readfile($filePath);
    }

    return;
}

require_once __DIR__ . '/public/index.php';
