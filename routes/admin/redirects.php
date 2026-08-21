<?php

use Illuminate\Support\Facades\Route;

/*
 * Legacy URL -> new-grammar redirect map (Task 3 of the admin-v2 IA plan).
 * Every family below moved when routes/web.php's admin group was split
 * into routes/admin/{section}.php per the design spec's canonical URL map.
 * This file is scaffolding, deletable after the next major release (see
 * the spec's "Legacy URLs" section).
 *
 * Route::redirect($old, $new, 301) is NOT usable here: its destination is a
 * plain string, but the admin prefix is runtime/config-driven
 * (config('admin.prefix')), not a compile-time constant. Every entry below
 * instead resolves its destination via route($name, ...) — the SAME frozen
 * route name the destination page already answers to in its new section
 * file — so the redirect always lands wherever that name is actually
 * mounted, with zero risk of a hand-typed new-URL string drifting out of
 * sync with the real route. Query strings (search, pagination, etc.) are
 * forwarded so a bookmarked filtered/paginated URL still works after the
 * redirect.
 *
 * Ordering matters within a few families: a literal sibling
 * ('.../rules', '.../templates', ...) must be registered BEFORE a
 * same-depth wildcard sibling ('.../{id}') or the wildcard would swallow
 * it first. Each block below preserves the exact relative order those
 * routes had in the pre-split routes/web.php admin group, so precedence
 * behaves exactly as it always did.
 *
 * GET-only by design: these are pages people bookmark/navigate to, not
 * form-action targets. POST/PUT/DELETE endpoints are never bookmarked, and
 * every view already resolves them via route() (unchanged names), so they
 * already point at the new URIs with no redirect needed.
 */
// Guarded: this file is require()'d (not require_once) every time
// routes/admin.php's group registers, which happens once per Application
// boot — and the test suite boots a fresh Application per test. A bare
// `function adminLegacyRedirect() {}` would fatal with "cannot redeclare"
// on the second test in the same PHPUnit process, since PHP function
// symbols are process-global, not per-Application.
if (! function_exists('adminLegacyRedirect')) {
    function adminLegacyRedirect(string $old, string $name): void
    {
        Route::get($old, function (...$params) use ($name) {
            // Resolved at request time on purpose: a legacy URL whose
            // target lives in an extension only resolves while that
            // extension is enabled. Disabled extension = honest 404,
            // never a RouteNotFoundException 500 (flat-rates/create
            // shipped that way with shipping-flat-rate switched off).
            if (! Route::has($name)) {
                abort(404);
            }

            $url = route($name, $params);

            if ($qs = request()->getQueryString()) {
                $url .= (str_contains($url, '?') ? '&' : '?').$qs;
            }

            return redirect($url, 301);
        });
    }
}

// Dashboard
adminLegacyRedirect('/', 'admin.dashboard');
adminLegacyRedirect('palette/search', 'admin.palette.search');
adminLegacyRedirect('address/countries', 'admin.address.countries');
adminLegacyRedirect('address/zones', 'admin.address.zones');

// Catalog — Products
adminLegacyRedirect('products', 'admin.products.index');
adminLegacyRedirect('products/create', 'admin.products.create');
adminLegacyRedirect('products/{product}/edit', 'admin.products.edit');
adminLegacyRedirect('products/images/browse', 'admin.products.images.browse');

// Catalog — Categories
adminLegacyRedirect('categories', 'admin.categories.index');
adminLegacyRedirect('categories/create', 'admin.categories.create');
adminLegacyRedirect('categories/{category}/edit', 'admin.categories.edit');

// Catalog — Brands
adminLegacyRedirect('brands/search', 'admin.brands.search');
adminLegacyRedirect('brands', 'admin.brands.index');
adminLegacyRedirect('brands/create', 'admin.brands.create');
adminLegacyRedirect('brands/{brand}/edit', 'admin.brands.edit');

// Catalog — Media
// Only the page survives. media/folders and media/list were routes to a
// database library that never held a row, and redirecting to a route name
// that no longer exists is a 500, not a redirect.
adminLegacyRedirect('media', 'admin.media.index');

// Catalog — Tags
adminLegacyRedirect('tags', 'admin.tags.index');
adminLegacyRedirect('tags/create', 'admin.tags.create');
adminLegacyRedirect('tags/{tag}/edit', 'admin.tags.edit');

// Customers
adminLegacyRedirect('customers', 'admin.customers.index');
adminLegacyRedirect('customers/create', 'admin.customers.create');
adminLegacyRedirect('customers/{customer}/edit', 'admin.customers.edit');
adminLegacyRedirect('customers/{customer}/impersonate', 'admin.customers.impersonate');
adminLegacyRedirect('impersonate/stop', 'admin.impersonate.stop');
adminLegacyRedirect('customer-groups', 'admin.customer-groups.index');
adminLegacyRedirect('customer-groups/create', 'admin.customer-groups.create');
adminLegacyRedirect('customer-groups/{customerGroup}/edit', 'admin.customer-groups.edit');

// Sales — Orders (literal siblings before the {order} show wildcard)
adminLegacyRedirect('orders/search-products', 'admin.orders.search-products');
adminLegacyRedirect('orders/search-customers', 'admin.orders.search-customers');
adminLegacyRedirect('orders/create', 'admin.orders.create');
adminLegacyRedirect('orders/{order}/edit', 'admin.orders.edit');
adminLegacyRedirect('orders', 'admin.orders.index');
adminLegacyRedirect('orders/{order}', 'admin.orders.show');

// Sales — Order Statuses (now Settings)
adminLegacyRedirect('order-statuses', 'admin.order-statuses.index');

// Sales — Shipping Couriers (now Settings > Couriers)
adminLegacyRedirect('shipping-couriers', 'admin.shipping-couriers.index');

// Sales — Fulfillment (operational queue) + Settings > Modules > Courier
// Fulfillment (config).
//
// NOT via adminLegacyRedirect(): that helper resolves the new URL through
// route($name), which is exactly right for every route above because those
// targets are core routes that always exist. courier-fulfillment is an
// optional, disableable extension (ExtensionManager) — its
// 'admin.fulfillment.*' names only exist while it's currently installed
// AND enabled — so route() would throw RouteNotFoundException (a 500, not
// a redirect) the moment a visitor hits an old bookmarked '/fulfillment'
// URL while the extension happens to be disabled. adminLegacyExtensionRedirect()
// below builds the destination as a literal path instead, so the redirect
// always succeeds regardless of the extension's current state — same
// reasoning as legacy-banners-redirect (routes/admin.php) for the same
// class of problem, just kept inside this file (unlike banners' redirect,
// courier-fulfillment's old mount already respected config('admin.prefix'),
// so it belongs in the normal prefixed admin. group here, not registered
// standalone).
if (! function_exists('adminLegacyExtensionRedirect')) {
    function adminLegacyExtensionRedirect(string $old, string $destination): void
    {
        Route::get($old, function (...$params) use ($destination) {
            // vsprintf() with zero '%s' placeholders and an empty $params
            // array (the no-parameter routes below) just returns
            // $destination unchanged.
            $path = vsprintf($destination, $params);
            $url = '/'.trim(config('admin.prefix', 'admin').'/'.$path, '/');

            if ($qs = request()->getQueryString()) {
                $url .= (str_contains($url, '?') ? '&' : '?').$qs;
            }

            return redirect($url, 301);
        });
    }
}

// Ordered most-specific-first per house convention, though 'fulfillment/settings'
// (2 segments), 'fulfillment/{order}/label' (3 segments), and bare
// 'fulfillment' (1 segment) are different depths with no precedence hazard.
adminLegacyExtensionRedirect('fulfillment/settings', 'extensions/courier-fulfillment/settings');
adminLegacyExtensionRedirect('fulfillment/{order}/label', 'extensions/courier-fulfillment/%s/label');
adminLegacyExtensionRedirect('fulfillment', 'extensions/courier-fulfillment');

// Content
adminLegacyRedirect('blog', 'admin.blog.index');
adminLegacyRedirect('blog/create', 'admin.blog.create');
adminLegacyRedirect('blog/{blog}/edit', 'admin.blog.edit');
adminLegacyRedirect('blog-categories', 'admin.blog-categories.index');
adminLegacyRedirect('blog-categories/create', 'admin.blog-categories.create');
adminLegacyRedirect('blog-categories/{blogCategory}/edit', 'admin.blog-categories.edit');
adminLegacyRedirect('pages', 'admin.pages.index');
adminLegacyRedirect('pages/create', 'admin.pages.create');
adminLegacyRedirect('pages/{page}/edit', 'admin.pages.edit');

// Settings — key-value section pages, automations, api logs, modules
// (the bare '/settings' hub itself did not move — its URI is unchanged,
// so it needs no redirect entry)
adminLegacyRedirect('settings/section/{section}', 'admin.settings.section');
adminLegacyRedirect('automations', 'admin.automations.index');
adminLegacyRedirect('api-sync-logs', 'admin.api-sync-logs.index');
adminLegacyRedirect('modules', 'admin.extensions.index');

// Sales — Abandoned Carts (operational) + Settings > Modules > Abandoned
// Carts (config) — literal siblings before the {cart} show wildcard
adminLegacyRedirect('abandoned-carts/rules/create', 'admin.abandoned-carts.rules.create');
adminLegacyRedirect('abandoned-carts/rules/{rule}/edit', 'admin.abandoned-carts.rules.edit');
adminLegacyRedirect('abandoned-carts/rules', 'admin.abandoned-carts.rules');
adminLegacyRedirect('abandoned-carts/templates/create', 'admin.abandoned-carts.templates.create');
adminLegacyRedirect('abandoned-carts/templates/{emailTemplate}/edit', 'admin.abandoned-carts.templates.edit');
adminLegacyRedirect('abandoned-carts/templates', 'admin.abandoned-carts.templates');
adminLegacyRedirect('abandoned-carts/notifications', 'admin.abandoned-carts.notifications');
adminLegacyRedirect('abandoned-carts/recovered', 'admin.abandoned-carts.recovered');
adminLegacyRedirect('abandoned-carts/settings', 'admin.abandoned-carts.settings');
adminLegacyRedirect('abandoned-carts', 'admin.abandoned-carts.index');
adminLegacyRedirect('abandoned-carts/{cart}', 'admin.abandoned-carts.show');

// Sales — Stock Notifications (now Settings > Stock Alerts)
adminLegacyRedirect('stock-notifications', 'admin.stock-notifications.index');

// Sales — Reviews
adminLegacyRedirect('reviews', 'admin.reviews.index');
adminLegacyRedirect('reviews/{review}', 'admin.reviews.show');

// Marketing — Coupons
adminLegacyRedirect('coupons', 'admin.coupons.index');
adminLegacyRedirect('coupons/create', 'admin.coupons.create');
adminLegacyRedirect('coupons/{coupon}/edit', 'admin.coupons.edit');
adminLegacyRedirect('coupons/search/products', 'admin.coupons.search-products');
adminLegacyRedirect('coupons/search/categories', 'admin.coupons.search-categories');
adminLegacyRedirect('coupons/search/brands', 'admin.coupons.search-brands');

// Settings — Email Templates
adminLegacyRedirect('email-templates', 'admin.email-templates.index');
adminLegacyRedirect('email-templates/{emailTemplate}/edit', 'admin.email-templates.edit');

// Marketing — SEO workspace, and Settings > Modules > SEO (engine config).
// Literal siblings before the {type} entities wildcard.
// ('extensions/seo/settings' is NOT redirected: since the wave-0 move it
// is a real page again — the engine settings' own home.)
Route::get('extensions/seo/{type}', function (string $type) {
    $url = route('admin.extensions.seo.entities', ['type' => $type]);

    if ($qs = request()->getQueryString()) {
        $url .= '?'.$qs;
    }

    return redirect($url, 301);
})->where('type', 'products|categories|brands|blog|pages');

// Settings — Users, Permissions
adminLegacyRedirect('users', 'admin.users.index');
adminLegacyRedirect('users/create', 'admin.users.create');
adminLegacyRedirect('users/{user}/edit', 'admin.users.edit');
// The Permissions matrix page became the User Groups CRUD (each group
// carries its own permission config), so both spellings land there.
adminLegacyRedirect('permissions', 'admin.user-groups.index');
adminLegacyRedirect('settings/permissions', 'admin.user-groups.index');

// Settings — Localization
adminLegacyRedirect('localization', 'admin.localization.index');
adminLegacyRedirect('localization/geo-zones/{geoZone}/edit', 'admin.localization.edit-geo-zone');

// Settings — Payments
adminLegacyRedirect('payment-settings', 'admin.payment-settings.index');
adminLegacyRedirect('payment-settings/{method}', 'admin.payment-settings.show');
adminLegacyRedirect('bank-accounts/{bankAccount}/edit', 'admin.bank-accounts.edit');
adminLegacyRedirect('payment-restrictions/create', 'admin.payment-restrictions.create');
adminLegacyRedirect('payment-restrictions/{paymentRestriction}/edit', 'admin.payment-restrictions.edit');
adminLegacyRedirect('payment-restrictions', 'admin.payment-restrictions.index');

// Settings — Shipping
adminLegacyRedirect('shipping-settings', 'admin.shipping-settings.index');
adminLegacyRedirect('free-shipping-rules', 'admin.free-shipping-rules.index');
adminLegacyRedirect('free-shipping-rules/create', 'admin.free-shipping-rules.create');
adminLegacyRedirect('free-shipping-rules/{freeShippingRule}/edit', 'admin.free-shipping-rules.edit');
adminLegacyRedirect('flat-rates/create', 'admin.flat-rates.create');
adminLegacyRedirect('flat-rates/{flatRate}/edit', 'admin.flat-rates.edit');
adminLegacyRedirect('weight-based-methods/create', 'admin.weight-based-methods.create');
adminLegacyRedirect('weight-based-methods/{weightBasedMethod}/edit', 'admin.weight-based-methods.edit');

// Settings — Theme
adminLegacyRedirect('theme', 'admin.theme.index');

// Extension architecture wave 0: the whole settings/modules subtree moved
// to the top-level /extensions area (the directory, the lifecycle, and
// every extension's settings mounted under the extensions/{name}
// convention). Path-preserving so every bookmarked module page lands on
// its new home.
Route::get('settings/modules/{path?}', function (?string $path = null) {
    $destination = '/'.trim(config('admin.prefix', 'admin').'/extensions/'.($path ?? ''), '/');
    $query = request()->getQueryString();

    return redirect($destination.($query ? '?'.$query : ''), 301);
})->where('path', '.*')->name('legacy-modules-redirect');

Route::get('settings/payments/{path?}', function (?string $path = null) {
    $destination = '/'.trim(config('admin.prefix', 'admin').'/extensions/payments/'.($path ?? ''), '/');
    $query = request()->getQueryString();

    return redirect($destination.($query ? '?'.$query : ''), 301);
})->where('path', '.*')->name('legacy-payments-redirect');

Route::get('settings/shipping/{path?}', function (?string $path = null) {
    $destination = '/'.trim(config('admin.prefix', 'admin').'/extensions/shipping/'.($path ?? ''), '/');
    $query = request()->getQueryString();

    return redirect($destination.($query ? '?'.$query : ''), 301);
})->where('path', '.*')->name('legacy-shipping-redirect');

Route::get('settings/stock-alerts/{path?}', function (?string $path = null) {
    $destination = '/'.trim(config('admin.prefix', 'admin').'/extensions/stock-alerts/'.($path ?? ''), '/');

    return redirect($destination, 301);
})->where('path', '.*')->name('legacy-stock-alerts-redirect');

Route::get('settings/theme/{path?}', function (?string $path = null) {
    $destination = '/'.trim(config('admin.prefix', 'admin').'/extensions/theme/'.($path ?? ''), '/');
    $query = request()->getQueryString();

    return redirect($destination.($query ? '?'.$query : ''), 301);
})->where('path', '.*')->name('legacy-theme-redirect');

// One-home re-mount (owner ruling 2026-08-19): every split extension's
// operational pages moved under its extensions/{name} home. Path- and
// query-preserving, same shape as the settings/* family above.
foreach ([
    'sales/abandoned-carts' => 'extensions/abandoned-carts',
    'sales/reviews' => 'extensions/reviews',
    'sales/fulfillment' => 'extensions/courier-fulfillment',
    'marketing/seo' => 'extensions/seo',
    'marketing/coupons' => 'extensions/coupons',
    'content/blog-categories' => 'extensions/blog/categories',
    'content/blog' => 'extensions/blog/posts',
] as $old => $new) {
    Route::get($old.'/{path?}', function (?string $path = null) use ($new) {
        $destination = '/'.trim(config('admin.prefix', 'admin').'/'.$new.'/'.($path ?? ''), '/');
        $query = request()->getQueryString();

        return redirect($destination.($query ? '?'.$query : ''), 301);
    })->where('path', '.*');
}
