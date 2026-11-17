<?php

namespace App\Support;

/**
 * Values read once per storefront request and then kept for the rest of it.
 *
 * With the database cache store every Cache::get() is a query, and a page
 * reads the same few keys again and again: the extension registry from
 * every place that asks whether an extension is on, the badge lists once per
 * product card, the currencies once per price. Keeping each value for the
 * request turns those into one read each.
 *
 * Only a routed request is memoized. Before routing (service providers
 * booting) and outside HTTP (console commands, queue jobs, migrations) every
 * call reads through, so a long-running worker or an install that creates
 * tables mid-process never sees a value from before its own change. Code
 * that changes the underlying data calls forget() with the same key, as it
 * already forgets the cache entry.
 */
final class RequestCache
{
    /** @var \WeakMap<object, array<string, mixed>>|null */
    private static ?\WeakMap $values = null;

    /**
     * @template T
     *
     * @param  \Closure(): T  $resolve
     * @return T
     */
    public static function remember(string $key, \Closure $resolve): mixed
    {
        $request = self::request();
        if ($request === null) {
            return $resolve();
        }

        self::$values ??= new \WeakMap();
        $values = self::$values[$request] ?? [];
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        $value = $resolve();
        $values[$key] = $value;
        self::$values[$request] = $values;

        return $value;
    }

    /** Drop one value (or every value whose key starts with $key when $prefix is true). */
    public static function forget(string $key, bool $prefix = false): void
    {
        $request = self::request();
        if ($request === null || self::$values === null || ! isset(self::$values[$request])) {
            return;
        }

        $values = self::$values[$request];
        foreach (array_keys($values) as $k) {
            if ($k === $key || ($prefix && str_starts_with($k, $key))) {
                unset($values[$k]);
            }
        }
        self::$values[$request] = $values;
    }

    public static function flush(): void
    {
        self::$values = null;
    }

    private static function request(): ?\Illuminate\Http\Request
    {
        if (! app()->bound('request')) {
            return null;
        }
        $request = app('request');

        return $request instanceof \Illuminate\Http\Request && $request->route() !== null ? $request : null;
    }
}
