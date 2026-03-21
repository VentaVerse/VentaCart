<?php

use App\Services\ThemeService;

if (!function_exists('theme')) {
    function theme(string $key, mixed $default = null): mixed
    {
        return app(ThemeService::class)->get($key, $default);
    }
}

if (!function_exists('theme_json')) {
    function theme_json(string $key, mixed $default = []): mixed
    {
        return app(ThemeService::class)->getJson($key, $default);
    }
}
