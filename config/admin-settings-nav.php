<?php

/*
 * The Settings area: the hub's cards, the sub-menu beside every settings
 * page, the command palette's Settings entries and the settings trails all
 * read this one list.
 *
 * Each item: 'key' is its URL segment (settings/{key}); 'label' is the page
 * title, word for word; 'line' is the card's one-line description;
 * 'keywords' are extra words the command palette matches. A non-null
 * 'section' is a page drawn by admin.settings.section; null means the item
 * is a page of its own at 'route'.
 *
 * A module's settings live with the module, in the Extensions area.
 */
return [
    [
        'label' => 'Store',
        'items' => [
            ['key' => 'general', 'line' => 'Store identity, contact, timezone, and default meta.', 'label' => 'General', 'icon' => 'store', 'route' => 'admin.settings.section', 'section' => 'general', 'keywords' => ['site name', 'identity', 'timezone', 'meta', 'logo', 'contact', 'email', 'phone', 'address', 'sender', 'from address']],
            ['key' => 'currencies', 'line' => 'Currency list and exchange rates.', 'label' => 'Currencies', 'icon' => 'currency', 'route' => 'admin.settings.section', 'section' => 'currencies', 'keywords' => ['exchange rate', 'forex', 'symbol']],
            ['key' => 'localization', 'line' => 'Countries, provinces, and geo zones.', 'label' => 'Localization', 'icon' => 'globe', 'route' => 'admin.localization.index', 'section' => null, 'keywords' => ['countries', 'provinces', 'geo zones', 'regions']],
        ],
    ],
    [
        'label' => 'Commerce',
        'items' => [
            ['key' => 'catalog', 'line' => 'Products per page, sorting, filters, and badges.', 'label' => 'Catalog', 'icon' => 'categories', 'route' => 'admin.settings.section', 'section' => 'catalog', 'keywords' => ['products per page', 'sort', 'filters', 'badges', 'new', 'hot']],
            ['key' => 'order-statuses', 'line' => 'Status flow and stock actions.', 'label' => 'Order statuses', 'icon' => 'orders', 'route' => 'admin.order-statuses.index', 'section' => null, 'keywords' => ['workflow', 'stock action', 'decrement', 'pending']],
            // Checkout and stock rules govern selling, not the store's identity.
            ['key' => 'checkout', 'line' => 'Guest checkout and stock rules.', 'label' => 'Checkout & Stock', 'icon' => 'cart', 'route' => 'admin.settings.section', 'section' => 'checkout', 'keywords' => ['guest', 'out of stock', 'low stock', 'threshold']],
        ],
    ],
    [
        'label' => 'Communication',
        'items' => [
            ['key' => 'mail', 'line' => 'Mail server and test sends.', 'label' => 'Notifications', 'icon' => 'mail', 'route' => 'admin.settings.section', 'section' => 'mail', 'keywords' => ['mail', 'smtp', 'email']],
            ['key' => 'email-templates', 'line' => 'Transactional email content.', 'label' => 'Email Templates', 'icon' => 'content', 'route' => 'admin.email-templates.index', 'section' => null, 'keywords' => ['order confirmation', 'notification', 'template']],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['key' => 'users', 'line' => 'Admin accounts.', 'label' => 'Users', 'icon' => 'customers', 'route' => 'admin.users.index', 'section' => null, 'keywords' => ['admins', 'accounts', 'password']],
            ['key' => 'user-groups', 'line' => 'Groups and their permissions.', 'label' => 'User Groups', 'icon' => 'shield', 'route' => 'admin.user-groups.index', 'section' => null, 'keywords' => ['roles', 'access', 'permissions']],
            ['key' => 'security', 'line' => 'Admin URL and login protection.', 'label' => 'Security', 'icon' => 'lock', 'route' => 'admin.settings.section', 'section' => 'security', 'keywords' => ['turnstile', 'captcha', 'admin prefix', 'login']],
            ['key' => 'automations', 'line' => 'Scheduled jobs and run-now.', 'label' => 'Automations', 'icon' => 'clock', 'route' => 'admin.settings.section', 'section' => 'automations', 'keywords' => ['cron', 'schedule', 'jobs', 'sitemap', 'currency update']],
        ],
    ],
];
