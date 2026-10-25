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

if (!function_exists('store_money')) {
    /**
     * Format an amount in the store's own (default) currency, unconverted.
     * For amounts already charged, such as an order's lines and totals: the
     * shopper's chosen display currency must not restate what was paid.
     */
    function store_money(mixed $amount): string
    {
        $currency = app(ThemeService::class)->getDefaultCurrency();

        return $currency ? money($amount, $currency) : number_format((float) $amount, 2);
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

if (!function_exists('thumb_url')) {
    /**
     * The small version of an image, when one was ever made.
     *
     * The product list paints twenty 40x40 boxes and was filling each one from
     * the full-size original: eighteen of twenty measured 1440x1080, the
     * largest 280KB for forty pixels, and images were 1.87MB of a 2.40MB page.
     *
     * ImageUploadService writes a `_thumb` beside everything it uploads, so
     * for anything uploaded through Venta the small file already exists. The
     * migrated OpenCart catalogue has none, and those fall back to the
     * original rather than to a broken image. One stat per row on a local
     * disk, memoised, so a repeated path costs nothing.
     */
    function thumb_url(?string $path): string
    {
        if (! $path) {
            return '';
        }

        static $resolved = [];

        if (! array_key_exists($path, $resolved)) {
            $info = pathinfo($path);
            $candidate = ($info['dirname'] ?? '') . '/' . ($info['filename'] ?? '') . '_thumb.' . ($info['extension'] ?? 'jpg');
            $candidate = ltrim($candidate, '/');

            $resolved[$path] = \Illuminate\Support\Facades\Storage::disk('public')->exists($candidate)
                ? $candidate
                : $path;
        }

        return storage_url($resolved[$path]);
    }
}

if (!function_exists('theme_json')) {
    function theme_json(string $key, mixed $default = []): mixed
    {
        return app(ThemeService::class)->getJson($key, $default);
    }
}
