<?php

// Venta Admin v3 work sidebar. Lean by design: the objects a merchant
// acts on, the module spaces, and the door to Settings. Items whose
// route is not registered (extension off) drop out at render.
return [
    'work' => [
        ['key' => 'home', 'label' => 'Home', 'icon' => 'home', 'route' => 'admin.dashboard'],
        ['key' => 'orders', 'label' => 'Orders', 'icon' => 'orders', 'route' => 'admin.orders.index'],
        ['key' => 'products', 'label' => 'Products', 'icon' => 'products', 'route' => 'admin.products.index'],
        ['key' => 'customers', 'label' => 'Customers', 'icon' => 'customers', 'route' => 'admin.customers.index'],
        ['key' => 'content', 'label' => 'Content', 'icon' => 'content', 'route' => 'admin.pages.index'],
        ['key' => 'marketing', 'label' => 'Marketing', 'icon' => 'marketing', 'route' => 'admin.coupons.index'],
    ],
    'modules' => [
        ['key' => 'seo', 'label' => 'SEO', 'icon' => 'seo', 'route' => 'admin.extensions.seo.dashboard'],
        ['key' => 'fulfillment', 'label' => 'Fulfillment', 'icon' => 'fulfillment', 'route' => 'admin.fulfillment.index'],
        ['key' => 'abandoned-carts', 'label' => 'Abandoned Carts', 'icon' => 'cart', 'route' => 'admin.abandoned-carts.index'],
    ],
];
