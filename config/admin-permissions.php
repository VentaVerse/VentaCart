<?php

// Which permission each admin route needs. Read by
// App\Support\AdminPermissions, which explains the value forms; in short:
//
//   'group'         GET needs group.view, a write needs group.create, edit
//                   or delete, picked from the route name and the method;
//   'group.action'  that one permission for every method;
//   '@admin'        any signed-in admin;
//   '@super-admin'  super admin only.
//
// A route that no entry covers is super admin only. The test
// tests/Feature/Admin/Permissions/EveryAdminRouteIsMappedTest.php walks every
// registered admin route and fails on one nobody mapped, so a new screen
// gets a decision rather than a default.
//
// Bundled extensions are mapped here so a fresh install is complete; a
// third-party extension declares its own routes (extension.json
// "admin_permissions", or AdminPermissions::map() from its provider).

$any = '@admin';

return [
    'routes' => [
        // Open to every admin: their own account, the search box (which
        // filters its own results by permission), address lookups that
        // forms use, and old URLs that only redirect.
        'admin.' => $any,
        'admin.legacy-*' => $any,
        'admin.profile.*' => $any,
        'admin.palette.search' => $any,
        'admin.impersonate.stop' => $any,
        'admin.address.*' => $any,

        'admin.dashboard' => 'dashboard.view',
        'admin.dashboard.*' => 'dashboard.view',

        // Sales
        'admin.orders.*' => 'orders',
        'admin.orders.history.*' => 'orders.edit',
        'admin.orders.validate-coupon' => 'orders.edit',
        'admin.shipping-couriers.*' => 'couriers',
        'admin.fulfillment.*' => 'orders',
        'admin.fulfillment.settings' => 'extensions',
        'admin.fulfillment.settings.*' => 'extensions',
        'admin.fulfillment.pickup-addresses.*' => 'extensions',
        'admin.fulfillment.test-connection' => 'extensions.edit',
        'admin.reviews.*' => 'reviews',
        'admin.reviews.settings' => 'extensions',
        'admin.abandoned-carts.*' => 'marketing',
        'admin.abandoned-carts.settings' => 'extensions',
        'admin.abandoned-carts.settings.*' => 'extensions',

        // Catalog
        'admin.products.*' => 'products',
        'admin.products.images.*' => 'products.edit',
        'admin.categories.*' => 'categories',
        'admin.brands.*' => 'brands',
        'admin.tags.*' => 'tags',
        'admin.attributes.*' => 'attributes',
        'admin.media.*' => 'media',

        // Customers
        'admin.customers.*' => 'customers',
        'admin.customers.impersonate' => 'customers.impersonate',
        'admin.customer-groups.*' => 'customer-groups',
        'admin.store-credit.*' => 'customers',

        // Marketing
        'admin.coupons.*' => 'marketing',
        'admin.coupons.settings' => 'extensions',
        'admin.email-campaigns.*' => 'marketing',
        'admin.banners.*' => 'marketing',
        'admin.stock-notifications.*' => 'marketing',
        'admin.extensions.seo.*' => 'marketing',
        'admin.extensions.seo.settings' => 'extensions',
        'admin.extensions.seo.save-settings' => 'extensions.edit',
        'admin.extensions.seo.test-claude' => 'extensions.edit',
        'admin.extensions.seo.test-gsc' => 'extensions.edit',
        'admin.extensions.seo.upload-gsc' => 'extensions.edit',

        // Content
        'admin.pages.*' => 'pages',
        'admin.blog.*' => 'blog',
        'admin.blog-categories.*' => 'blog',
        'admin.menus' => 'menus.view',
        'admin.menus.*' => 'menus',
        'admin.menus.arrange' => 'menus.edit',

        // Layout and themes. Plaza and Xelga register their own editors
        // under these names; they are mapped here so the editors open for
        // whoever may edit themes.
        'admin.layout' => 'layout.view',
        'admin.layout.*' => 'layout',
        'admin.theme.*' => 'themes',
        'admin.theme.currencies.*' => 'settings',
        'admin.plaza.*' => 'themes.edit',
        'admin.xelga.*' => 'themes.edit',

        // Extensions: the directory, modules, and each module's own
        // configuration pages.
        'admin.extensions.*' => 'extensions',
        // Uploading a package installs code: even the screen is super
        // admin only.
        'admin.extensions.upload' => '@super-admin',
        'admin.extensions.upload.*' => '@super-admin',
        'admin.payment-settings.*' => 'extensions',
        'admin.payment-restrictions.*' => 'extensions',
        'admin.bank-accounts.*' => 'extensions',
        'admin.shipping-settings.*' => 'extensions',
        'admin.flat-rates.*' => 'extensions',
        'admin.free-shipping-rules.*' => 'extensions',
        'admin.weight-based-methods.*' => 'extensions',
        'admin.erp-sync.*' => 'extensions',

        // Settings
        'admin.settings.*' => 'settings',
        'admin.localization.*' => 'settings',
        'admin.order-statuses.*' => 'settings',
        'admin.email-templates.*' => 'settings',
        'admin.automations.*' => 'settings',
        'admin.api-sync-logs.*' => 'settings',

        // Users and user groups. Reading needs users.view; every write is
        // super admin only (see below).
        'admin.users.*' => 'users',
        'admin.user-groups.*' => 'users',
    ],

    // Writes to these routes are super admin only, whatever a role holds:
    // they decide who can sign in, run code the store did not ship with, or
    // hand out secrets. Reading (GET) still follows the map above.
    'super_admin_only' => [
        'admin.users.*',
        'admin.user-groups.*',
        'admin.extensions.install',
        'admin.extensions.uninstall',
        'admin.extensions.upload',
        'admin.extensions.upload.*',
        'admin.theme.package.*',
        'admin.theme.presets.import',
        'admin.layout.templates.import',
        'admin.settings.reveal-secret',
        'admin.erp-sync.keys.*',
    ],
];
