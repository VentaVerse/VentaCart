<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Which KIND of storefront page is being rendered.
 *
 * The theme editor lets a merchant override the look per page type: all
 * product pages share one set, all account pages another. Types, not URLs,
 * because a store with 1,880 products would otherwise accumulate 1,880
 * override sets and a merchant would have to restyle each one.
 *
 * Resolution is by route NAME, never by path. Paths carry slugs a merchant
 * controls (/shoes, /about-us) and would change under the feature's feet; a
 * route name is the developer's own word for what the page is.
 *
 * An unrecognised route returns null and simply gets no overrides. A page we
 * did not anticipate rendering with the store's global look is correct; the
 * alternative, guessing it into some bucket, styles it from a set the merchant
 * never chose for it.
 *
 * Core, not the theme extension: overrides live in settings and must keep
 * applying to the storefront whether or not the editor is installed.
 */
class StorefrontPageType
{
    /** Ordered as a merchant would walk the store, not alphabetically. */
    public const TYPES = [
        'home'     => 'Home',
        'category' => 'Category pages',
        'product'  => 'Product pages',
        'search'   => 'Search results',
        'brand'    => 'Brand pages',
        'blog'     => 'Blog',
        'page'     => 'Content pages',
        'cart'     => 'Cart',
        'checkout' => 'Checkout',
        'account'  => 'Account pages',
        'auth'     => 'Sign in and register',
    ];

    /**
     * Route name prefixes, longest-specific first. Order matters: 'customer.'
     * covers login and registration, which belong with auth rather than with
     * the signed-in account area.
     */
    private const MAP = [
        'home'             => 'home',
        'category.'        => 'category',
        'product.'         => 'product',
        'search.'          => 'search',
        'brand.'           => 'brand',
        'blog.'            => 'blog',
        'page.'            => 'page',
        'cart.'            => 'cart',
        'checkout.'        => 'checkout',
        'account.'         => 'account',
        'customer.'        => 'auth',
        'login'            => 'auth',
        'register'         => 'auth',
        'password.'        => 'auth',
    ];

    /**
     * Page types an extension added, by type: its label and route name
     * prefixes. Registered from a service provider at boot, so a type whose
     * extension is switched off is simply absent.
     *
     * @var array<string, array{label: string, routes: list<string>}>
     */
    private static array $registered = [];

    /**
     * Let an extension add a kind of storefront page, so a theme can tell
     * it apart from core's pages. Core's own types and their routes
     * cannot be taken over: a core type's name is refused, and core's route
     * prefixes are matched before any registered one.
     *
     * @param  string  $type  lower case letters, digits and underscores, e.g. 'shop'
     * @param  list<string>  $routes  route name prefixes, e.g. ['marketplace.shop.']
     */
    public static function register(string $type, string $label, array $routes): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,39}$/', $type) !== 1) {
            throw new \InvalidArgumentException("A page type is lower case letters, digits and underscores: [{$type}].");
        }
        if (array_key_exists($type, self::TYPES) || in_array($type, ['quickview', 'global'], true)) {
            throw new \InvalidArgumentException("[{$type}] is one of core's page types.");
        }
        $routes = array_values(array_filter($routes, fn ($r) => is_string($r) && $r !== ''));
        if ($routes === []) {
            throw new \InvalidArgumentException("The page type [{$type}] needs at least one route name prefix.");
        }

        self::$registered[$type] = ['label' => trim($label) !== '' ? trim($label) : ucfirst($type), 'routes' => $routes];
    }

    public static function forget(string $type): void
    {
        unset(self::$registered[$type]);
    }

    /**
     * Every page type, core's first in their order, then the ones extensions
     * added.
     *
     * @return array<string, string> type => label
     */
    public static function all(): array
    {
        return self::TYPES + array_map(fn ($t) => $t['label'], self::$registered);
    }

    public static function current(): ?string
    {
        return self::forRoute(Route::currentRouteName());
    }

    public static function forRoute(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        foreach (self::MAP as $prefix => $type) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return $type;
            }
        }
        foreach (self::$registered as $type => $spec) {
            foreach ($spec['routes'] as $prefix) {
                if ($name === $prefix || str_starts_with($name, $prefix)) {
                    return $type;
                }
            }
        }

        return null;
    }

    public static function label(?string $type): ?string
    {
        return self::all()[$type] ?? null;
    }

    public static function exists(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::all());
    }
}
