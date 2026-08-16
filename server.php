<?php

// Dev router for `php artisan serve` (a root server.php overrides the
// framework's bundled router). Same rewrite emulation, plus real
// caching headers for static assets: the built-in server sends none,
// so every navigation re-downloaded every hashed asset and font,
// which read as a font flash and stalled the nav view transitions.
$publicPath = getcwd();

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    // Vite-hashed build assets are immutable by construction; storage
    // files and fonts change rarely in dev. Returning false would serve
    // them header-less, so serve these ourselves with Cache-Control.
    $cache = null;
    if (str_starts_with($uri, '/build/assets/')) {
        $cache = 'public, max-age=31536000, immutable';
    } elseif (str_starts_with($uri, '/storage/') || str_starts_with($uri, '/fonts/') || str_starts_with($uri, '/vendor/')) {
        $cache = 'public, max-age=3600';
    }

    if ($cache !== null) {
        $file = realpath($publicPath.$uri);
        if ($file !== false && str_starts_with($file, realpath($publicPath).DIRECTORY_SEPARATOR)) {
            $types = [
                'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript',
                'woff2' => 'font/woff2', 'woff' => 'font/woff',
                'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon', 'map' => 'application/json', 'json' => 'application/json',
            ];
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            header('Cache-Control: '.$cache);
            header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
            header('Content-Length: '.(string) filesize($file));
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
                readfile($file);
            }
            return true;
        }
    }

    return false;
}

require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
