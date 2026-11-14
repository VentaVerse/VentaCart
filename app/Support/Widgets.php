<?php

namespace App\Support;

use App\Services\ThemeService;

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
 *
 * Widgets are kept by owner. Core's and the extensions' widgets are shared:
 * every theme can place them. A theme's own widgets (registered with a
 * 'theme') are kept with that theme, and every read sees the shared ones plus
 * the current theme's own. Every installed theme boots, live or not, so its
 * editor works before it goes live; keeping its widgets apart is what stops
 * them from reaching another theme's editor or storefront.
 */
class Widgets
{
    /** The owner shared widgets are kept under. */
    private const SHARED = '';

    /** @var array<string, array<string, array>> owner => key => spec */
    private static array $registry = [];

    /**
     * @param string $key    dotted, e.g. 'catalog.products'. The dot is also
     *                       the view path: widgets/catalog/products.blade.php
     * @param array  $spec   title, resolve (callable|null), view, module,
     *                       regions (allow-list|null), schema
     */
    public static function register(string $key, array $spec): void
    {
        self::$registry[(string) ($spec['theme'] ?? self::SHARED)][$key] = $spec + [
            'title' => $key,
            'resolve' => null,
            'view' => null,
            'module' => null,
            'regions' => null,
            // Page types this widget makes sense on, or null for any. A
            // widget that reads the page's own subject (the product being
            // viewed) declares where that subject exists.
            'pages' => null,
            // A theme's own piece (its header, its product gallery), offered
            // where the theme declares a system region for it.
            'system' => false,
            // The theme package that registers this widget, when one does.
            // It is kept with that theme: no other theme sees it.
            'theme' => null,
            // Core ships a plain view of its own (resources/views/widgets),
            // so a theme that declares the region need not draw it.
            'optional' => false,
            // How this widget lays out, so the picker can DRAW it instead of
            // naming it. A merchant choosing between "Product tabs" and
            // "Brand strip" is choosing between two shapes, and the words are
            // the worst available description of a shape. Not an image: a
            // wireframe the editor draws, so a third-party widget gets one by
            // declaring a word rather than by shipping an asset.
            'shape' => null,
            'schema' => [],
        ];
    }

    public static function forget(string $key): void
    {
        foreach (array_keys(self::$registry) as $owner) {
            unset(self::$registry[$owner][$key]);
        }
    }

    /**
     * Every widget a theme can place: the shared ones plus its own. A theme's
     * own widget wins over a shared one with the same key, inside that theme.
     *
     * @param  string|null  $theme  the theme asking; the current one when null
     */
    public static function all(?string $theme = null): array
    {
        $theme ??= self::currentTheme();
        $shared = self::$registry[self::SHARED] ?? [];

        return $theme === self::SHARED ? $shared : array_replace($shared, self::$registry[$theme] ?? []);
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function get(string $key): ?array
    {
        $theme = self::currentTheme();

        return ($theme !== self::SHARED ? self::$registry[$theme][$key] ?? null : null)
            ?? self::$registry[self::SHARED][$key]
            ?? null;
    }

    /**
     * The theme this request draws or edits: the live one, or the one a
     * theme's editor scopes its requests to. Shared widgets only when there
     * is no theme to ask about yet (no settings table on a fresh install).
     */
    private static function currentTheme(): string
    {
        try {
            return app(ThemeService::class)->getActiveTheme();
        } catch (\Throwable) {
            return self::SHARED;
        }
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
     * A widget's settings form, with any dynamic choices resolved.
     *
     * A schema can name a source instead of a fixed option list, because the
     * choices are the merchant's own data: which banner group, which category.
     * Resolving here means the editor renders a schema and never learns what a
     * banner group is.
     */
    public static function schema(string $key): array
    {
        $spec = self::get($key);

        if (! $spec) {
            return [];
        }

        $out = [];

        foreach ($spec['schema'] as $name => $field) {
            if (isset($field['options']) && is_callable($field['options'])) {
                try {
                    $field['options'] = (array) call_user_func($field['options']);
                } catch (\Throwable $e) {
                    // One widget that cannot list its choices must not take the
                    // layout editor down with it. A module mid-install, or one
                    // whose tables are gone, degrades to an empty list and the
                    // merchant can still arrange every other widget.
                    $field['options'] = [];
                    $field['unavailable'] = true;
                }
            }

            // The same choices again, with enough to recognise one on sight
            // and the address of its own editor. This is what lets the layout
            // editor open a banner group without knowing what one is: the
            // module hands over rows, core renders rows.
            if (isset($field['browse']) && is_callable($field['browse'])) {
                try {
                    $field['browse'] = self::rows((array) call_user_func($field['browse']));
                } catch (\Throwable $e) {
                    $field['browse'] = [];
                    $field['unavailable'] = true;
                }
            }

            // The fields of a repeating row may list their choices the same
            // way, and they must reach the editor as plain lists too.
            if (isset($field['fields']) && is_array($field['fields'])) {
                foreach ($field['fields'] as $sub => $subField) {
                    if (is_array($subField) && isset($subField['options']) && is_callable($subField['options'])) {
                        try {
                            $field['fields'][$sub]['options'] = (array) call_user_func($subField['options']);
                        } catch (\Throwable $e) {
                            $field['fields'][$sub]['options'] = [];
                            $field['fields'][$sub]['unavailable'] = true;
                        }
                    }
                }
            }

            $field['name'] = $name;
            $field['type'] = $field['type'] ?? 'text';
            $field['label'] = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
            $out[] = $field;
        }

        return $out;
    }

    /**
     * One browse row, normalised and framed.
     *
     * A module returns what it knows; this decides what the editor may
     * rely on. The url is stamped with the embed flag HERE, so a module
     * hands over the plain route it already has and never learns that the
     * editor puts it in a panel.
     *
     * @param  array<int, array>  $rows
     */
    private static function rows(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ($row['value'] ?? null) === null) {
                continue;
            }

            $url = $row['url'] ?? null;

            $out[] = [
                'value' => (string) $row['value'],
                'label' => (string) ($row['label'] ?? $row['value']),
                'meta' => isset($row['meta']) ? (string) $row['meta'] : null,
                // Thumbnails are decoration: a row without them still works,
                // so a missing image never costs the merchant the row.
                'thumbs' => array_values(array_filter(array_map(
                    fn ($t) => is_string($t) && $t !== '' ? $t : null,
                    (array) ($row['thumbs'] ?? [])
                ))),
                'url' => $url ? AdminFrame::url($url) : null,
            ];
        }

        return $out;
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
