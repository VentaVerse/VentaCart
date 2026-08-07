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
return [
    [
        'label' => null, // ungrouped
        'items' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'permission' => null, 'keywords' => ['home', 'overview']],
        ],
    ],
    [
        'label' => 'Sales',
        'segment' => 'sales',
        'items' => [
            ['key' => 'orders', 'label' => 'Orders', 'route' => 'admin.orders.index', 'icon' => 'orders', 'permission' => null, 'keywords' => ['sales', 'invoices']],
            ['key' => 'fulfillment', 'label' => 'Fulfillment', 'route' => 'admin.fulfillment.index', 'icon' => 'fulfillment', 'permission' => null, 'keywords' => ['booking', 'waybill', 'awb', 'tracking', 'quadx', 'courier']],
            ['key' => 'abandoned-carts', 'label' => 'Abandoned Carts', 'route' => 'admin.abandoned-carts.index', 'icon' => 'cart', 'permission' => null, 'keywords' => ['recovery']],
            ['key' => 'reviews', 'label' => 'Reviews', 'route' => 'admin.reviews.index', 'icon' => 'reviews', 'permission' => null, 'keywords' => ['ratings', 'stars']],
        ],
    ],
    [
        'label' => 'Catalog',
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
        'segment' => 'content',
        'items' => [
            ['key' => 'pages', 'label' => 'Pages', 'route' => 'admin.pages.index', 'icon' => 'pages', 'permission' => null, 'keywords' => ['cms', 'static']],
            ['key' => 'blog', 'label' => 'Blog Posts', 'route' => 'admin.blog.index', 'icon' => 'blog', 'permission' => null, 'keywords' => ['articles', 'news']],
            ['key' => 'blog-categories', 'label' => 'Blog Categories', 'route' => 'admin.blog-categories.index', 'icon' => 'categories', 'permission' => null, 'keywords' => []],
        ],
    ],
    [
        'label' => 'Marketing',
        'segment' => 'marketing',
        'items' => [
            ['key' => 'coupons', 'label' => 'Coupons', 'route' => 'admin.coupons.index', 'icon' => 'coupons', 'permission' => null, 'keywords' => ['discount', 'promo', 'voucher']],
            ['key' => 'seo', 'label' => 'SEO', 'route' => 'admin.extensions.seo.dashboard', 'icon' => 'seo', 'permission' => null, 'keywords' => ['search', 'meta', 'suggestions']],
        ],
    ],
    [
        'label' => 'System',
        'segment' => 'settings',
        'items' => [
            ['key' => 'settings', 'label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'settings', 'permission' => null, 'keywords' => ['configuration', 'preferences', 'hub']],
        ],
    ],
];
