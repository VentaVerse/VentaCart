<?php

namespace App\Support;

use App\Services\ExtensionManager;

/**
 * The Venta Theme Contract, as code: what a theme package must contain to
 * be installed. The written form is docs/theme-contract.md; the two agree,
 * and a theme that passes here works with Layout, the widgets, and every
 * storefront route.
 */
final class ThemeContract
{
    /** Regions every theme draws, so a merchant's placements survive a switch. */
    public const REQUIRED_REGIONS = ['header_top', 'content_top', 'content_bottom', 'footer_top'];

    /** Views core routes render, as view names; the theme owns every one. */
    public const REQUIRED_VIEWS = [
        'layouts.store', 'home', 'category.index', 'product.show', 'brand.index', 'brand.show',
        'search.results', 'cart.index', 'checkout.index', 'checkout.confirmation', 'page.show', 'contact',
        'account.dashboard', 'account.login', 'account.register', 'account.forgot-password',
        'account.reset-password', 'account.profile', 'account.orders', 'account.order-detail',
        'account.addresses', 'account.address-form', 'partials.product-card',
    ];

    /** Views an extension's routes render, required while that extension exists. */
    public const EXTENSION_VIEWS = [
        'blog' => ['blog.index', 'blog.show'],
        'reviews' => ['review.submit', 'review.thankyou'],
    ];

    /**
     * Views a theme may leave out; core answers 404, draws nothing, or (for
     * the wishlist) draws its own view inside the theme's layout.
     */
    public const OPTIONAL_VIEWS = ['product.quick-view', 'maintenance', 'sitemap', 'wishlist.index'];

    /** Setting types a theme's own editor is expected to draw (docs/theme-contract.md, 2.2). */
    public const SETTING_TYPES = ['layout', 'select', 'toggle', 'text', 'textarea', 'number', 'color', 'font', 'media', 'box', 'multi', 'rules', 'images'];

    /**
     * Everything wrong with the package at $path, as sentences. Empty means
     * the theme may be installed.
     *
     * @return list<string>
     */
    public static function check(string $path): array
    {
        $problems = [];
        $json = rtrim($path, '/') . '/theme.json';

        if (! is_file($json)) {
            return ['theme.json is missing.'];
        }

        $meta = json_decode((string) file_get_contents($json), true);

        if (! is_array($meta)) {
            return ['theme.json is not valid JSON.'];
        }

        foreach (['name', 'version'] as $key) {
            if (empty($meta[$key]) || ! is_string($meta[$key])) {
                $problems[] = "theme.json needs a {$key}.";
            }
        }

        $regions = array_keys((array) ($meta['regions'] ?? []));
        if ($missing = array_diff(self::REQUIRED_REGIONS, $regions)) {
            $problems[] = 'theme.json regions is missing ' . implode(', ', $missing) . '.';
        }

        foreach ((array) ($meta['regions'] ?? []) as $region => $spec) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', (string) $region)) {
                $problems[] = "Region [{$region}] is not a valid region key (lowercase letters, digits, underscores).";
            }
            if (is_array($spec) && ! empty($spec['defaults']) && ! is_array($spec['defaults'])) {
                $problems[] = "Region [{$region}] defaults must be a list of widget keys.";
            }
            // A shipped row that would collapse to one column on the
            // storefront is a mistake in the manifest, not a layout.
            foreach (is_array($spec['defaults'] ?? null) ? $spec['defaults'] : [] as $entry) {
                if (is_array($entry) && isset($entry['cols'])) {
                    $widths = array_values((array) ($entry['columns'] ?? []));
                    if (! is_array($entry['cols']) || ColumnGrid::fromManifest($meta['grid'] ?? null)->normalizeColumns($widths) !== $widths || count($widths) !== count($entry['cols'])) {
                        $problems[] = "Region [{$region}] has a default row whose columns do not add up on the theme's grid (eight parts unless theme.json declares a grid), one list of pieces per column.";
                    }
                }
            }
        }

        foreach ((array) ($meta['settings'] ?? []) as $key => $spec) {
            if (! is_array($spec) || empty($spec['type']) || empty($spec['label'])) {
                $problems[] = "Setting [{$key}] needs a type and a label.";
            } elseif (! in_array($spec['type'], self::SETTING_TYPES, true)) {
                $problems[] = "Setting [{$key}] has an unknown type: {$spec['type']}.";
            }
        }

        if (! is_file(rtrim($path, '/') . '/css/theme.css')) {
            $problems[] = 'css/theme.css is missing.';
        }

        $required = self::REQUIRED_VIEWS;
        $discovered = app(ExtensionManager::class)->discovered();
        foreach (self::EXTENSION_VIEWS as $extension => $views) {
            if ($discovered->has($extension)) {
                $required = array_merge($required, $views);
            }
        }

        // Every widget this theme can place (the shared ones and its own)
        // must have a view in the theme, except system pieces, which are only
        // required where the theme declares the region they belong to.
        $slug = basename(rtrim($path, '/'));
        foreach (Widgets::all($slug) as $key => $spec) {
            // A namespaced view belongs to the extension that registered
            // the widget, not to the theme.
            if (empty($spec['view']) || str_contains($spec['view'], '::')) {
                continue;
            }
            // Core draws this one when the theme does not.
            if (! empty($spec['optional'])) {
                continue;
            }
            if (! empty($spec['system'])) {
                $wanted = array_intersect((array) ($spec['regions'] ?? []), $regions);
                if ($wanted === []) {
                    continue;
                }
            }
            $required[] = $spec['view'];
        }

        foreach (array_unique($required) as $view) {
            if (! is_file(rtrim($path, '/') . '/views/' . str_replace('.', '/', $view) . '.blade.php')) {
                $problems[] = "View [{$view}] is missing.";
            }
        }

        return $problems;
    }

    /** Whether the package at $path may be installed. */
    public static function passes(string $path): bool
    {
        return self::check($path) === [];
    }
}
