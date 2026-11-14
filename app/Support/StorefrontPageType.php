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

        return null;
    }

    /**
     * What each kind of page draws by itself, between the layout positions,
     * in one plain sentence. Shown in the layout editor so a merchant can tell
     * the page's own content from the widgets they placed around it.
     */
    private const BODY = [
        'home'     => 'Nothing of its own: everything on the home page comes from the widgets placed in its positions.',
        'category' => 'The category: its name, description, filters and the products in it.',
        'product'  => 'The product: images, name, price, variants, Add to cart, then the description and reviews.',
        'search'   => 'The search results for what the shopper typed.',
        'brand'    => 'The brand: its name, logo and its products.',
        'blog'     => 'The blog list or the post being read.',
        'page'     => 'The content page you wrote in Content > Pages.',
        'cart'     => 'The cart: its items, totals and the button to check out.',
        'checkout' => 'The checkout form: address, shipping, payment and order summary.',
        'account'  => 'The customer\'s account: orders, addresses and details.',
        'auth'     => 'The sign in and register forms.',
    ];

    public static function body(?string $type): ?string
    {
        return $type === null ? null : (self::BODY[$type] ?? null);
    }

    public static function label(?string $type): ?string
    {
        return self::TYPES[$type] ?? null;
    }

    public static function exists(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::TYPES);
    }
}
