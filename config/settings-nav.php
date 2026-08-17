<?php

// Single source of truth for the Settings hub. Drives the hub card grid,
// the section sub-sidebar, and command-palette entries. 'section' non-null
// means the destination is a section page rendered by SettingsSectionController
// (route admin.settings.section); null means an external admin page that has
// not migrated into the section shell yet.
return [
    [
        'label' => 'Store',
        'items' => [
            ['key' => 'general', 'label' => 'General', 'description' => 'Store identity, timezone, and default meta tags.', 'icon' => 'settings', 'route' => 'admin.settings.section', 'section' => 'general', 'permission' => null, 'keywords' => ['site name', 'identity', 'timezone', 'meta', 'logo']],
            ['key' => 'mail', 'label' => 'Mail', 'description' => 'SMTP server, sender identity, and test sending.', 'icon' => 'pages', 'route' => 'admin.settings.section', 'section' => 'mail', 'permission' => null, 'keywords' => ['smtp', 'email', 'sender', 'from address']],
            ['key' => 'checkout', 'label' => 'Checkout & Stock', 'description' => 'Guest checkout, out-of-stock rules, low-stock alerts.', 'icon' => 'cart', 'route' => 'admin.settings.section', 'section' => 'checkout', 'permission' => null, 'keywords' => ['guest', 'out of stock', 'low stock', 'threshold']],
            ['key' => 'currencies', 'label' => 'Currencies', 'description' => 'Currency list, symbols, and exchange-rate updates.', 'icon' => 'coupons', 'route' => 'admin.settings.section', 'section' => 'currencies', 'permission' => null, 'keywords' => ['exchange rate', 'peso', 'php', 'forex', 'symbol']],
            ['key' => 'localization', 'label' => 'Localization', 'description' => 'Countries, zones, and geo zones.', 'icon' => 'media', 'route' => 'admin.localization.index', 'section' => null, 'permission' => null, 'keywords' => ['countries', 'provinces', 'geo zones', 'regions']],
            ['key' => 'theme', 'label' => 'Theme', 'description' => 'Storefront appearance, homepage sections, navigation.', 'icon' => 'tags', 'route' => 'admin.theme.index', 'section' => null, 'permission' => null, 'keywords' => ['storefront', 'colors', 'fonts', 'homepage', 'logo', 'favicon']],
        ],
    ],
    [
        'label' => 'Operations',
        'items' => [
            ['key' => 'order-statuses', 'label' => 'Order Statuses', 'description' => 'Status flow and automatic stock actions.', 'icon' => 'orders', 'route' => 'admin.order-statuses.index', 'section' => null, 'permission' => null, 'keywords' => ['workflow', 'stock action', 'decrement', 'pending']],
            ['key' => 'couriers', 'label' => 'Couriers & Tracking', 'description' => 'Courier list and tracking-URL templates.', 'icon' => 'fulfillment', 'route' => 'admin.shipping-couriers.index', 'section' => null, 'permission' => null, 'keywords' => ['tracking url', 'lbc', 'jnt', 'waybill']],
            ['key' => 'email-templates', 'label' => 'Email Templates', 'description' => 'Transactional email content and test sends.', 'icon' => 'pages', 'route' => 'admin.email-templates.index', 'section' => null, 'permission' => null, 'keywords' => ['order confirmation', 'notification', 'template']],
            ['key' => 'automations', 'label' => 'Automations', 'description' => 'Scheduled jobs: schedules, status, run now.', 'icon' => 'settings', 'route' => 'admin.settings.section', 'section' => 'automations', 'permission' => null, 'keywords' => ['cron', 'schedule', 'jobs', 'sitemap', 'currency update']],
        ],
    ],
    [
        'label' => 'Access & system',
        'items' => [
            ['key' => 'users', 'label' => 'Users', 'description' => 'Admin accounts.', 'icon' => 'customers', 'route' => 'admin.users.index', 'section' => null, 'permission' => null, 'keywords' => ['admins', 'accounts', 'password']],
            ['key' => 'permissions', 'label' => 'Permissions', 'description' => 'Role-based access control.', 'icon' => 'groups', 'route' => 'admin.permissions.index', 'section' => null, 'permission' => null, 'keywords' => ['roles', 'access', 'rbac']],
            ['key' => 'security', 'label' => 'Security', 'description' => 'Admin URL prefix and Turnstile protection.', 'icon' => 'check', 'route' => 'admin.settings.section', 'section' => 'security', 'permission' => null, 'keywords' => ['turnstile', 'captcha', 'admin prefix', 'login']],
            ['key' => 'api', 'label' => 'API & Sync', 'description' => 'ERP token and sync logging.', 'icon' => 'external', 'route' => 'admin.settings.section', 'section' => 'api', 'permission' => null, 'keywords' => ['erp', 'token', 'sync logs', 'integration']],
            // key equals the URL's modules/{key} segment ({p}/settings/modules/seo/settings),
            // not the display label — see App\Support\Breadcrumbs.
            ['key' => 'seo', 'label' => 'SEO Engine', 'description' => 'Claude API, generation rules, and strategy.', 'icon' => 'seo', 'route' => 'admin.extensions.seo.settings', 'section' => null, 'permission' => null, 'keywords' => ['claude', 'meta generation', 'suggestions', 'gsc']],
        ],
    ],
];
