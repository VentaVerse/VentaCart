<?php

namespace App\Support;

use App\Services\ThemeService;
use Illuminate\Support\Facades\Storage;

/**
 * The store's own name and marks, for the admin chrome.
 *
 * The admin shows the MERCHANT, never the platform: whoever is signed in
 * runs a store, and Venta is only the application it runs on. The values
 * come from the same settings the storefront reads (group 'identity',
 * paths on the public disk), resolved the same way, so the two never
 * disagree about what the store is called.
 *
 * The one thing this adds over the storefront's inline lookup is an
 * existence check. A settings row outlives the file it names: a logo
 * deleted from storage, or a database restored onto a machine that never
 * had the uploads, leaves a path pointing at nothing. The storefront
 * renders a broken image in that case; the admin falls back to the mark
 * and the name, which is the same treatment as never having uploaded one.
 */
class StoreIdentity
{
    /** Resolved once per request: three settings and at most two stats. */
    protected static ?array $memo = null;

    /** The store's name, falling back to the application's. */
    public static function name(): string
    {
        return static::resolve()['name'];
    }

    /** Absolute URL of the uploaded logo, or null when there isn't a usable one. */
    public static function logoUrl(): ?string
    {
        return static::resolve()['logo'];
    }

    /** Absolute URL of the uploaded favicon, or null. */
    public static function faviconUrl(): ?string
    {
        return static::resolve()['favicon'];
    }

    /** First character of the store's name, for the fallback mark. */
    public static function initial(): string
    {
        return mb_strtoupper(mb_substr(static::name(), 0, 1));
    }

    /** @return array{name: string, logo: ?string, favicon: ?string} */
    protected static function resolve(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        $theme = app(ThemeService::class);

        return static::$memo = [
            'name' => $theme->get('identity.site_name') ?: config('app.name', 'Venta'),
            'logo' => static::url($theme->get('identity.logo')),
            'favicon' => static::url($theme->get('identity.favicon')),
        ];
    }

    protected static function url(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        try {
            if (! Storage::disk('public')->exists($path)) {
                return null;
            }
        } catch (\Throwable) {
            // An unreachable disk must not be the thing that takes the admin
            // panel down; the name alone is a complete identity.
            return null;
        }

        return asset('storage/' . $path);
    }

    /** Test seam: forget the per-request memo. */
    public static function flush(): void
    {
        static::$memo = null;
    }
}
