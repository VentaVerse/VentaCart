<?php

// Single source of truth for admin navigation. The sidebar, breadcrumbs,
// and command palette all render from this file. 'permission' of null =
// visible to every admin; otherwise checked via User::hasPermission().
// 'keywords' are palette/search synonyms, never displayed. 'segment'
// (groups) and 'key' (items) are the URL-grammar segments the path-derived
// nav (App\Support\Breadcrumbs, App\Support\AdminNav::activeItemKey())
// matches against — they MUST equal the real URI segment, renamed here
// wherever the display label diverges from it (e.g. 'Customer Groups' item
// lives at the 'groups' URL segment, not 'customer-groups').
//
// 'enabled_setting' (optional) => ['group' => ..., 'key' => ...] names a row
// in the settings table that switches the item off: the entry hides while
// that setting is explicitly '0', and shows whenever it is missing or
// anything else (opt-out, so a never-configured feature is visible). See
// App\Support\AdminNav::settingAllows(). Its page must keep working while
// hidden: hiding a menu entry is not a permission check, and a bookmarked
// URL should explain its own state rather than 404.
return [
    [
        'label' => null, // ungrouped
        'items' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'permission' => null, 'keywords' => ['home', 'overview']],
        ],
    ],
    [
        'label' => 'Sales',
        'icon' => 'orders',
        'segment' => 'sales',
        'items' => [
            ['key' => 'orders', 'label' => 'Orders', 'route' => 'admin.orders.index', 'icon' => 'orders', 'permission' => null, 'keywords' => ['sales', 'invoices']],
            ['key' => 'reviews', 'label' => 'Reviews', 'route' => 'admin.reviews.index', 'icon' => 'reviews', 'permission' => null, 'keywords' => ['ratings', 'stars']],
        ],
    ],
    [
        'label' => 'Catalog',
        'icon' => 'products',
        'segment' => 'catalog',
        'items' => [
            ['key' => 'products', 'label' => 'Products', 'route' => 'admin.products.index', 'icon' => 'products', 'permission' => null, 'keywords' => ['items', 'sku', 'inventory']],
            ['key' => 'categories', 'label' => 'Categories', 'route' => 'admin.categories.index', 'icon' => 'categories', 'permission' => null, 'keywords' => []],
            ['key' => 'brands', 'label' => 'Brands', 'route' => 'admin.brands.index', 'icon' => 'brands', 'permission' => null, 'keywords' => ['manufacturers']],
            ['key' => 'media', 'label' => 'Media', 'route' => 'admin.media.index', 'icon' => 'media', 'permission' => null, 'keywords' => ['images', 'files']],
            ['key' => 'tags', 'label' => 'Tags', 'route' => 'admin.tags.index', 'icon' => 'tags', 'permission' => null, 'keywords' => []],
        ],
    ],
    [
        'label' => 'Customers',
        'icon' => 'customers',
        'segment' => 'customers',
        'items' => [
            ['key' => 'customers', 'label' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'customers', 'permission' => null, 'keywords' => ['buyers', 'accounts']],
            // URL segment is 'groups' ({p}/customers/groups), not 'customer-groups' — the
            // route name (frozen since Task 3) stays 'customer-groups.*'.
            ['key' => 'groups', 'label' => 'Customer Groups', 'route' => 'admin.customer-groups.index', 'icon' => 'groups', 'permission' => null, 'keywords' => ['resellers', 'wholesale']],
        ],
    ],
    [
        'label' => 'Content',
        'icon' => 'pages',
        'segment' => 'content',
        'items' => [
            ['key' => 'pages', 'label' => 'Pages', 'route' => 'admin.pages.index', 'icon' => 'pages', 'permission' => null, 'keywords' => ['cms', 'static']],
            ['key' => 'blog', 'label' => 'Blog Posts', 'route' => 'admin.blog.index', 'icon' => 'blog', 'permission' => null, 'keywords' => ['articles', 'news']],
            ['key' => 'blog-categories', 'label' => 'Blog Categories', 'route' => 'admin.blog-categories.index', 'icon' => 'categories', 'permission' => null, 'keywords' => []],
        ],
    ],
    [
        'label' => 'Marketing',
        'icon' => 'coupons',
        'segment' => 'marketing',
        'items' => [
            ['key' => 'coupons', 'label' => 'Coupons', 'route' => 'admin.coupons.index', 'icon' => 'coupons', 'permission' => null, 'keywords' => ['discount', 'promo', 'voucher']],
            ['key' => 'seo', 'label' => 'SEO', 'route' => 'admin.extensions.seo.dashboard', 'icon' => 'seo', 'permission' => null, 'keywords' => ['search', 'meta', 'suggestions'], 'enabled_setting' => ['group' => 'features', 'key' => 'seo_enabled']],
        ],
    ],
    [
        'label' => 'Extensions',
        'icon' => 'modules',
        'segment' => 'extensions',
        'items' => [
            // The directory is the group's landing (URL /extensions, no
            // child segment); activeItemKey() lights a group's first item
            // at its root URL. Typed category children (Payments, Shipping)
            // join as their pages re-home (wave 0 pieces 2b/2c).
            ['key' => 'all', 'label' => 'All Extensions', 'route' => 'admin.extensions.index', 'icon' => 'modules', 'permission' => null, 'keywords' => ['extensions', 'plugins', 'install']],
            ['key' => 'modules', 'label' => 'Modules', 'route' => 'admin.extensions.modules', 'icon' => 'modules', 'permission' => null, 'keywords' => ['modules', 'features', 'banners', 'seo', 'stock alerts', 'abandoned carts']],
            ['key' => 'payments', 'label' => 'Payments', 'route' => 'admin.payment-settings.index', 'icon' => 'payments', 'permission' => null, 'keywords' => ['bank transfer', 'cod', 'hitpay', 'gateway', 'restrictions']],
            ['key' => 'shipping', 'label' => 'Shipping', 'route' => 'admin.shipping-settings.index', 'icon' => 'shipping', 'permission' => null, 'keywords' => ['rates', 'flat rate', 'weight', 'free shipping', 'dhl', 'couriers']],
        ],
    ],
    [
        // Settings is a door, not a dropdown: one click leaves the work
        // area and opens the Settings space (the main nav condenses to
        // the icon rail there).
        'label' => null,
        'items' => [
            ['key' => 'settings', 'label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'settings', 'permission' => null, 'keywords' => ['configuration', 'preferences', 'hub', 'system']],
        ],
    ],
];
