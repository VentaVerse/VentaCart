<?php

// Single source of truth for admin navigation. The sidebar, breadcrumbs,
// and command palette all render from this file. 'permission' of null =
// visible to every admin; otherwise checked via User::hasPermission().
// 'keywords' are palette/search synonyms, never displayed.
return [
    [
        'label' => null, // ungrouped
        'items' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'pattern' => 'admin.dashboard', 'permission' => null, 'keywords' => ['home', 'overview']],
        ],
    ],
    [
        'label' => 'Sales',
        'items' => [
            ['label' => 'Orders', 'route' => 'admin.orders.index', 'icon' => 'orders', 'pattern' => 'admin.orders*', 'permission' => null, 'keywords' => ['sales', 'invoices']],
            // ['label' => 'Fulfillment', 'route' => 'admin.fulfillment.index', 'icon' => 'fulfillment', 'pattern' => 'admin.fulfillment*', 'permission' => null, 'keywords' => ['quadx', 'lbc', 'spx', 'booking', 'waybill']], // enable when route exists
            ['label' => 'Abandoned Carts', 'route' => 'admin.abandoned-carts.index', 'icon' => 'cart', 'pattern' => 'admin.abandoned-carts*', 'permission' => null, 'keywords' => ['recovery']],
            ['label' => 'Reviews', 'route' => 'admin.reviews.index', 'icon' => 'reviews', 'pattern' => 'admin.reviews*', 'permission' => null, 'keywords' => ['ratings', 'stars']],
        ],
    ],
    [
        'label' => 'Catalog',
        'items' => [
            ['label' => 'Products', 'route' => 'admin.products.index', 'icon' => 'products', 'pattern' => 'admin.products*', 'permission' => null, 'keywords' => ['items', 'sku', 'inventory']],
            ['label' => 'Categories', 'route' => 'admin.categories.index', 'icon' => 'categories', 'pattern' => 'admin.categories*', 'permission' => null, 'keywords' => []],
            ['label' => 'Brands', 'route' => 'admin.brands.index', 'icon' => 'brands', 'pattern' => 'admin.brands*', 'permission' => null, 'keywords' => ['manufacturers']],
            ['label' => 'Media', 'route' => 'admin.media.index', 'icon' => 'media', 'pattern' => 'admin.media*', 'permission' => null, 'keywords' => ['images', 'files']],
            ['label' => 'Tags', 'route' => 'admin.tags.index', 'icon' => 'tags', 'pattern' => 'admin.tags*', 'permission' => null, 'keywords' => []],
        ],
    ],
    [
        'label' => 'Customers',
        'items' => [
            ['label' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'customers', 'pattern' => 'admin.customers*', 'permission' => null, 'keywords' => ['buyers', 'accounts']],
            ['label' => 'Customer Groups', 'route' => 'admin.customer-groups.index', 'icon' => 'groups', 'pattern' => 'admin.customer-groups*', 'permission' => null, 'keywords' => ['resellers', 'wholesale']],
        ],
    ],
    [
        'label' => 'Content',
        'items' => [
            ['label' => 'Pages', 'route' => 'admin.pages.index', 'icon' => 'pages', 'pattern' => 'admin.pages*', 'permission' => null, 'keywords' => ['cms', 'static']],
            ['label' => 'Blog Posts', 'route' => 'admin.blog.index', 'icon' => 'blog', 'pattern' => 'admin.blog.*', 'permission' => null, 'keywords' => ['articles', 'news']],
            ['label' => 'Blog Categories', 'route' => 'admin.blog-categories.index', 'icon' => 'categories', 'pattern' => 'admin.blog-categories*', 'permission' => null, 'keywords' => []],
        ],
    ],
    [
        'label' => 'Marketing',
        'items' => [
            ['label' => 'Coupons', 'route' => 'admin.coupons.index', 'icon' => 'coupons', 'pattern' => 'admin.coupons*', 'permission' => null, 'keywords' => ['discount', 'promo', 'voucher']],
            ['label' => 'SEO', 'route' => 'admin.extensions.seo.dashboard', 'icon' => 'seo', 'pattern' => 'admin.extensions.seo*', 'permission' => null, 'keywords' => ['search', 'meta', 'suggestions']],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'settings', 'pattern' => 'admin.settings*', 'permission' => null, 'keywords' => ['configuration', 'preferences', 'hub']],
        ],
    ],
];
