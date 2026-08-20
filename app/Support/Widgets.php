<?php

namespace App\Support;

/**
 * What can be placed, and how each one gets its data.
 *
 * This replaces a switch statement in HomeController that loaded products for
 * a 'products' section and brands for a 'brands' section. That switch meant
 * every new section type was a core edit, and it is the reason section types
 * were a closed set of eight owned by the application rather than something an
 * extension could add.
 *
 * A widget now carries its own data loading. Core never learns what a banner
 * group is; the banners extension registers one and resolves it.
 *
 * The registry is process-global static state, the same shape as the courier
 * registry: registered from service providers at boot, never mutated per
 * request. A test that registers one must unregister it after.
 */
class Widgets
{
    /** @var array<string, array> */
    private static array $registry = [];

    /**
     * @param string $key    dotted, e.g. 'catalog.products'. The dot is also
     *                       the view path: widgets/catalog/products.blade.php
     * @param array  $spec   title, resolve (callable|null), view, module,
     *                       regions (allow-list|null), schema
     */
    public static function register(string $key, array $spec): void
    {
        self::$registry[$key] = $spec + [
            'title' => $key,
            'resolve' => null,
            'view' => null,
            'module' => null,
            'regions' => null,
            'schema' => [],
        ];
    }

    public static function forget(string $key): void
    {
        unset(self::$registry[$key]);
    }

    public static function all(): array
    {
        return self::$registry;
    }

    public static function has(string $key): bool
    {
        return isset(self::$registry[$key]);
    }

    public static function get(string $key): ?array
    {
        return self::$registry[$key] ?? null;
    }

    /** Can this widget sit in this region, per its own declaration? */
    public static function allowedIn(string $key, string $region): bool
    {
        $spec = self::get($key);
        if (! $spec) {
            return false;
        }

        return $spec['regions'] === null || in_array($region, $spec['regions'], true);
    }

    /**
     * The data this widget's view receives, on top of $placement.
     *
     * Each widget documenting what it hands its view IS the storefront data
     * contract, discovered one widget at a time from real use rather than
     * designed in the abstract for an imaginary theme.
     */
    public static function resolve(string $key, array $config): array
    {
        $spec = self::get($key);
        if (! $spec || ! is_callable($spec['resolve'])) {
            return [];
        }

        return (array) call_user_func($spec['resolve'], $config);
    }

    /**
     * Theme first, module second.
     *
     * A theme overrides any widget by shipping widgets/{path}.blade.php, and
     * inherits the module's plain version when it does not care. This one rule
     * is the whole "the theme owns the look" argument: the Front Panel paints
     * products as inset spec plates without the catalog module knowing.
     */
    public static function view(string $key): ?string
    {
        $themeView = 'widgets.' . str_replace('.', '.', $key);

        if (view()->exists($themeView)) {
            return $themeView;
        }

        $spec = self::get($key);

        return $spec && $spec['view'] && view()->exists($spec['view']) ? $spec['view'] : null;
    }
}
