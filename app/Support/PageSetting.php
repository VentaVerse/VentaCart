<?php

namespace App\Support;

use App\Models\LayoutColumns;

/**
 * The parts of a hardcoded page a merchant has switched on or off.
 *
 * Not a reduction layer: the page is not being taken apart. Its parts simply
 * have controls, and hiding is one of them. The theme declares which parts in
 * theme.json; this reads the merchant's answer for the page being rendered.
 *
 * Resolved once per request. A storefront page asks this several times and
 * must not pay for a query each time.
 */
class PageSetting
{
    private static array $cache = [];

    public static function on(string $key, bool $default = true): bool
    {
        $type = StorefrontPageType::current();

        if ($type === null) {
            return $default;
        }

        if (! array_key_exists($type, self::$cache)) {
            self::$cache[$type] = LayoutColumns::forPage($type)['settings'] ?? [];
        }

        $value = self::$cache[$type][$key] ?? $default;

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** Tests and long-running workers need this; a web request never does. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
