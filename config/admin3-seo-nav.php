<?php

// The SEO space's own menu (the second column beside the icon rail),
// same grammar as admin3-settings-nav. 'params' ride the route call;
// 'key' is what pages pass as active.
return [
    [
        'label' => 'Work',
        'items' => [
            ['key' => 'dashboard', 'line' => 'Coverage and suggestion counts.', 'label' => 'Dashboard', 'icon' => 'home', 'route' => 'admin.extensions.seo.dashboard'],
            ['key' => 'review', 'line' => 'Accept or reject pending suggestions.', 'label' => 'Review', 'icon' => 'check', 'route' => 'admin.extensions.seo.review'],
            ['key' => 'slugs', 'line' => 'URL slugs across the catalog.', 'label' => 'Slugs', 'icon' => 'api', 'route' => 'admin.extensions.seo.slugs'],
        ],
    ],
    [
        'label' => 'Content',
        'items' => [
            ['key' => 'products', 'line' => 'Product meta.', 'label' => 'Products', 'icon' => 'products', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'products']],
            ['key' => 'categories', 'line' => 'Category meta.', 'label' => 'Categories', 'icon' => 'modules', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'categories']],
            ['key' => 'brands', 'line' => 'Brand meta.', 'label' => 'Brands', 'icon' => 'store', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'brands']],
            ['key' => 'blog', 'line' => 'Blog post meta.', 'label' => 'Blog', 'icon' => 'content', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'blog']],
            ['key' => 'pages', 'line' => 'Static page meta.', 'label' => 'Pages', 'icon' => 'content', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'pages']],
        ],
    ],
    [
        'label' => 'Traffic',
        'items' => [
            ['key' => 'search-console', 'line' => 'Google Search Console data.', 'label' => 'Search Console', 'icon' => 'seo', 'route' => 'admin.extensions.seo.search-console'],
            ['key' => 'redirects', 'line' => '301s and their hit counts.', 'label' => 'Redirects', 'icon' => 'shipping', 'route' => 'admin.extensions.seo.redirects.index'],
            ['key' => 'not-found', 'line' => 'URLs visitors miss on.', 'label' => '404s', 'icon' => 'warning', 'route' => 'admin.extensions.seo.not-found.index'],
        ],
    ],
    [
        'label' => 'Setup',
        'items' => [
            ['key' => 'strategy', 'line' => 'Tone and keyword guidance for the engine.', 'label' => 'Strategy', 'icon' => 'theme', 'route' => 'admin.extensions.seo.strategy'],
            ['key' => 'settings', 'line' => 'API key, model, and automation.', 'label' => 'Engine settings', 'icon' => 'settings', 'route' => 'admin.extensions.seo.settings'],
        ],
    ],
];
