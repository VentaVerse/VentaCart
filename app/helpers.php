<?php

use App\Services\ThemeService;

if (!function_exists('theme')) {
    function theme(string $key, mixed $default = null): mixed
    {
        return app(ThemeService::class)->get($key, $default);
    }
}

if (!function_exists('money')) {
    /**
     * Format a base-currency amount for display in the shopper's active currency.
     * Converts by the active currency's rate and prefixes its symbol. Display-only
     * — orders are still charged in the base (default) currency.
     */
    function money(mixed $amount, ?\App\Models\Currency $currency = null): string
    {
        $currency = $currency ?? app(ThemeService::class)->getActiveCurrency();
        $amount = (float) $amount;

        if (!$currency) {
            return number_format($amount, 2);
        }

        return $currency->symbol . number_format($amount * (float) $currency->rate, 2);
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
        return '/storage/' . $encoded;
    }
}

if (!function_exists('theme_json')) {
    function theme_json(string $key, mixed $default = []): mixed
    {
        return app(ThemeService::class)->getJson($key, $default);
    }
}
