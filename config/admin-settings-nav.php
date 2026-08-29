<?php

// Venta Admin v Settings area. General is general; a module's settings
// live with the module. 'section' non-null resolves through
// admin.settings.section; null items are standalone settings pages.
// Notifications when that page is rebuilt. Couriers moved to Sales
// (2026-08-28): order vocabulary, and core with no module installed.
return [
    [
        'label' => 'Store',
        'items' => [
            ['key' => 'general', 'line' => 'Store identity, timezone, and default meta.', 'label' => 'General', 'icon' => 'store', 'route' => 'admin.settings.section', 'section' => 'general'],
            ['key' => 'currencies', 'line' => 'Currency list and exchange rates.', 'label' => 'Currencies', 'icon' => 'currency', 'route' => 'admin.settings.section', 'section' => 'currencies'],
            ['key' => 'localization', 'line' => 'Countries, provinces, and geo zones.', 'label' => 'Localization', 'icon' => 'globe', 'route' => 'admin.localization.index', 'section' => null],
        ],
    ],
    [
        'label' => 'Commerce',
        'items' => [
            ['key' => 'order-statuses', 'line' => 'Status flow and stock actions.', 'label' => 'Order Status', 'icon' => 'orders', 'route' => 'admin.order-statuses.index', 'section' => null],
            // Owner ruling 2026-08-30: checkout rules and stock rules are commerce, not store identity.
            ['key' => 'checkout', 'line' => 'Guest checkout and stock rules.', 'label' => 'Checkout & Stock', 'icon' => 'cart', 'route' => 'admin.settings.section', 'section' => 'checkout'],
        ],
    ],
    [
        'label' => 'Communication',
        'items' => [
            ['key' => 'mail', 'line' => 'Mail server, sender, and test sends.', 'label' => 'Notifications', 'icon' => 'mail', 'route' => 'admin.settings.section', 'section' => 'mail'],
            ['key' => 'email-templates', 'line' => 'Transactional email content.', 'label' => 'Email Templates', 'icon' => 'content', 'route' => 'admin.email-templates.index', 'section' => null],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['key' => 'users', 'line' => 'Admin accounts.', 'label' => 'Users', 'icon' => 'customers', 'route' => 'admin.users.index', 'section' => null],
            ['key' => 'user-groups', 'line' => 'Groups and their permissions.', 'label' => 'User Groups', 'icon' => 'shield', 'route' => 'admin.user-groups.index', 'section' => null],
            ['key' => 'security', 'line' => 'Admin URL and login protection.', 'label' => 'Security', 'icon' => 'lock', 'route' => 'admin.settings.section', 'section' => 'security'],
            ['key' => 'automations', 'line' => 'Scheduled jobs and run-now.', 'label' => 'Automations', 'icon' => 'clock', 'route' => 'admin.settings.section', 'section' => 'automations'],
        ],
    ],
];
