<?php

namespace App\Support;

use App\Services\ExtensionManager;

/**
 * The theme contract, as code: what a theme package must contain to be
 * installed. The written form is docs/theme-contract.md; the two agree,
 * and a theme that passes here draws every storefront route.
 */
final class ThemeContract
{
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

        $required = ['name', 'version'];

        foreach ($required as $key) {
            if (empty($meta[$key]) || ! is_string($meta[$key])) {
                $problems[] = "theme.json needs a {$key}.";
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
