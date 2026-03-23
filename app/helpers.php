<?php

use App\Services\ThemeService;

if (!function_exists('theme')) {
    function theme(string $key, mixed $default = null): mixed
    {
        return app(ThemeService::class)->get($key, $default);
    }
}

if (!function_exists('storage_url')) {
    /**
     * Generate a URL for a file in the public storage disk.
     * Properly encodes special characters (like +) that break in URLs.
     */
    function storage_url(?string $path): string
    {
        if (!$path) return '';
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
        return asset('storage/' . $encoded);
    }
}

if (!function_exists('theme_json')) {
    function theme_json(string $key, mixed $default = []): mixed
    {
        return app(ThemeService::class)->getJson($key, $default);
    }
}
