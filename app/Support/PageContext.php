<?php

namespace App\Support;

/**
 * What the current page is about, published by its controller for the
 * widgets that draw the page's own pieces (the product being viewed, the
 * category being browsed). Lives on the container, so it is one request's.
 */
final class PageContext
{
    private const KEY = 'venta.page.context';

    /** @param array<string, mixed> $values */
    public static function set(array $values): void
    {
        app()->instance(self::KEY, array_merge(self::all(), $values));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return app()->bound(self::KEY) ? (array) app(self::KEY) : [];
    }
}
