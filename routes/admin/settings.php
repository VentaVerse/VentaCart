<?php

use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\Marketing\Seo\SeoExtensionController;
use App\Http\Controllers\Admin\Sales\AbandonedCartController;
use App\Http\Controllers\Admin\Settings\ApiSyncLogController;
use App\Http\Controllers\Admin\Settings\BankAccountController;
use App\Http\Controllers\Admin\Settings\CurrencyController;
use App\Http\Controllers\Admin\Settings\EmailTemplateController;
use App\Http\Controllers\Admin\Settings\FlatRateController;
use App\Http\Controllers\Admin\Settings\FooterColumnController;
use App\Http\Controllers\Admin\Settings\FreeShippingRuleController;
use App\Http\Controllers\Admin\Settings\HomepageSectionController;
use App\Http\Controllers\Admin\Settings\LocalizationController;
use App\Http\Controllers\Admin\Settings\ModuleController;
use App\Http\Controllers\Admin\Settings\NavMenuController;
use App\Http\Controllers\Admin\Settings\OrderStatusController;
use App\Http\Controllers\Admin\Settings\PaymentMethodPageController;
use App\Http\Controllers\Admin\Settings\PaymentRestrictionController;
use App\Http\Controllers\Admin\Settings\PaymentSettingController;
use App\Http\Controllers\Admin\Settings\RolePermissionController;
use App\Http\Controllers\Admin\Settings\SettingController;
use App\Http\Controllers\Admin\Settings\SettingsHubController;
use App\Http\Controllers\Admin\Settings\SettingsSectionController;
use App\Http\Controllers\Admin\Settings\ShippingCourierController;
use App\Http\Controllers\Admin\Settings\ShippingSettingController;
use App\Http\Controllers\Admin\Settings\ShippingTestController;
use App\Http\Controllers\Admin\Settings\StoreIdentityController;
use App\Http\Controllers\Admin\Settings\StockNotificationController as AdminStockNotificationController;
use App\Http\Controllers\Admin\Settings\ThemeController;
use App\Http\Controllers\Admin\Settings\UserController;
use App\Http\Controllers\Admin\Settings\WeightBasedMethodController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->group(function () {
    Route::get('/', [SettingsHubController::class, 'index'])->name('settings.index');
    Route::post('/', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/test-mail', [SettingController::class, 'testMail'])->name('settings.test-mail');
    Route::post('/test-dhl', [SettingController::class, 'testDhl'])->name('settings.test-dhl');
    Route::post('/generate-erp-token', [SettingController::class, 'generateErpToken'])->name('settings.generate-erp-token');
    Route::post('/upload-gsc-credentials', [SettingController::class, 'uploadGscCredentials'])->name('settings.upload-gsc-credentials');

    // The store's name and marks. Its own endpoint rather than the shared
    // key-value one because the marks are files: they need validation the
    // generic setter has no business carrying, and replacing one has to
    // delete the file it replaced.
    Route::post('/identity', [StoreIdentityController::class, 'update'])->name('settings.identity.update');
    Route::delete('/identity/{asset}', [StoreIdentityController::class, 'destroy'])
        ->whereIn('asset', ['logo', 'favicon'])
        ->name('settings.identity.destroy');

    // Automations (Settings > Automations section — DB-managed scheduled
    // tasks). AutomationController::index is a legacy stub that redirects
    // into the old tabbed settings page; it is nested one level deeper
    // than the section's own GET (settings/automations, handled by the
    // settings/{section} catch-all below) so the two don't collide.
    Route::get('/automations/index', [AutomationController::class, 'index'])->name('automations.index');
    Route::patch('/automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::post('/automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');
    Route::post('/automations/{automation}/run', [AutomationController::class, 'runNow'])->name('automations.runNow');

    // API Sync Logs
    Route::get('/api-logs', [ApiSyncLogController::class, 'index'])->name('api-sync-logs.index');
    Route::delete('/api-logs', [ApiSyncLogController::class, 'destroyAll'])->name('api-sync-logs.destroy-all');

    // Order Statuses
    Route::get('/order-statuses', [OrderStatusController::class, 'index'])->name('order-statuses.index');
    Route::post('/order-statuses', [OrderStatusController::class, 'store'])->name('order-statuses.store');
    Route::put('/order-statuses/{orderStatus}', [OrderStatusController::class, 'update'])->name('order-statuses.update');
    Route::delete('/order-statuses/{orderStatus}', [OrderStatusController::class, 'destroy'])->name('order-statuses.destroy');

    // Couriers & Tracking (configuration — operational booking, if any,
    // stays elsewhere; this is rate/tracking-URL setup)
    Route::get('/couriers', [ShippingCourierController::class, 'index'])->name('shipping-couriers.index');
    Route::post('/couriers', [ShippingCourierController::class, 'store'])->name('shipping-couriers.store');
    Route::put('/couriers/{shippingCourier}', [ShippingCourierController::class, 'update'])->name('shipping-couriers.update');
    Route::delete('/couriers/{shippingCourier}', [ShippingCourierController::class, 'destroy'])->name('shipping-couriers.destroy');

    // Email Templates
    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('/email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::post('/email-templates/{emailTemplate}/toggle', [EmailTemplateController::class, 'toggleActive'])->name('email-templates.toggle');
    Route::post('/email-templates/{emailTemplate}/test', [EmailTemplateController::class, 'sendTest'])->name('email-templates.send-test');

    // Stock Alerts
    Route::get('/stock-alerts', [AdminStockNotificationController::class, 'index'])->name('stock-notifications.index');
    Route::delete('/stock-alerts/{stockNotification}', [AdminStockNotificationController::class, 'destroy'])->name('stock-notifications.destroy');
    Route::post('/stock-alerts/bulk-delete', [AdminStockNotificationController::class, 'bulkDelete'])->name('stock-notifications.bulk-delete');
    Route::post('/stock-alerts/template', [AdminStockNotificationController::class, 'updateTemplate'])->name('stock-notifications.update-template');
    Route::post('/stock-alerts/test-email', [AdminStockNotificationController::class, 'sendTest'])->name('stock-notifications.send-test');

    // Localization
    Route::get('/localization', [LocalizationController::class, 'index'])->name('localization.index');
    Route::post('/localization/countries/{country}/toggle', [LocalizationController::class, 'toggleCountry'])->name('localization.toggle-country');
    Route::put('/localization/countries/{country}', [LocalizationController::class, 'updateCountry'])->name('localization.update-country');
    Route::post('/localization/zones/{zone}/toggle', [LocalizationController::class, 'toggleZone'])->name('localization.toggle-zone');
    Route::post('/localization/zones', [LocalizationController::class, 'storeZone'])->name('localization.store-zone');
    Route::put('/localization/zones/{zone}', [LocalizationController::class, 'updateZone'])->name('localization.update-zone');
    Route::delete('/localization/zones/{zone}', [LocalizationController::class, 'destroyZone'])->name('localization.destroy-zone');
    Route::post('/localization/geo-zones', [LocalizationController::class, 'storeGeoZone'])->name('localization.store-geo-zone');
    Route::get('/localization/geo-zones/{geoZone}/edit', [LocalizationController::class, 'editGeoZone'])->name('localization.edit-geo-zone');
    Route::put('/localization/geo-zones/{geoZone}', [LocalizationController::class, 'updateGeoZone'])->name('localization.update-geo-zone');
    Route::post('/localization/geo-zones/{geoZone}/entries', [LocalizationController::class, 'syncGeoZoneEntries'])->name('localization.sync-geo-zone-entries');
    Route::post('/localization/geo-zones/{geoZone}/toggle', [LocalizationController::class, 'toggleGeoZone'])->name('localization.toggle-geo-zone');
    Route::delete('/localization/geo-zones/{geoZone}', [LocalizationController::class, 'destroyGeoZone'])->name('localization.destroy-geo-zone');

    // Users
    Route::resource('users', UserController::class)->except(['show']);

    // Role Permissions
    Route::get('/permissions', [RolePermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions', [RolePermissionController::class, 'update'])->name('permissions.update');

    // Theme (storefront appearance: homepage sections, nav, footer). Site
    // currencies are their own settings-nav entry — see /currencies below.
    Route::prefix('theme')->group(function () {
        Route::get('/', [ThemeController::class, 'index'])->name('theme.index');
        Route::post('/settings', [ThemeController::class, 'updateSettings'])->name('theme.settings.update');
        Route::post('/switch', [ThemeController::class, 'switchTheme'])->name('theme.switch');
        Route::post('/revert-colors', [ThemeController::class, 'revertThemeColors'])->name('theme.revert-colors');
        Route::post('/theme-settings', [ThemeController::class, 'updateThemeSettings'])->name('theme.update-theme-settings');

        Route::post('/sections', [HomepageSectionController::class, 'store'])->name('theme.sections.store');
        Route::put('/sections/{section}', [HomepageSectionController::class, 'update'])->name('theme.sections.update');
        Route::delete('/sections/{section}', [HomepageSectionController::class, 'destroy'])->name('theme.sections.destroy');
        Route::post('/sections/reorder', [HomepageSectionController::class, 'reorder'])->name('theme.sections.reorder');

        Route::post('/nav', [NavMenuController::class, 'store'])->name('theme.nav.store');
        Route::put('/nav/{item}', [NavMenuController::class, 'update'])->name('theme.nav.update');
        Route::delete('/nav/{item}', [NavMenuController::class, 'destroy'])->name('theme.nav.destroy');
        Route::post('/nav/reorder', [NavMenuController::class, 'reorder'])->name('theme.nav.reorder');

        Route::post('/footer', [FooterColumnController::class, 'store'])->name('theme.footer.store');
        Route::post('/footer/bulk-update', [FooterColumnController::class, 'bulkUpdate'])->name('theme.footer.bulk-update');
        Route::put('/footer/{column}', [FooterColumnController::class, 'update'])->name('theme.footer.update');
        Route::delete('/footer/{column}', [FooterColumnController::class, 'destroy'])->name('theme.footer.destroy');
        Route::post('/footer/reorder', [FooterColumnController::class, 'reorder'])->name('theme.footer.reorder');
    });

    // Currencies (Settings > Store > Currencies section page's backing CRUD;
    // the page itself renders through the settings/{section} catch-all
    // below — there is deliberately no literal GET '/' here)
    Route::prefix('currencies')->group(function () {
        Route::post('/', [CurrencyController::class, 'store'])->name('theme.currencies.store');
        Route::put('/{currency}', [CurrencyController::class, 'update'])->name('theme.currencies.update');
        Route::delete('/{currency}', [CurrencyController::class, 'destroy'])->name('theme.currencies.destroy');
        Route::post('/fetch-rates', [CurrencyController::class, 'fetchRates'])->name('theme.currencies.fetch-rates');
    });

    // Payments
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentSettingController::class, 'index'])->name('payment-settings.index');

        // Bank Accounts (bank-transfer method's account list) — literal
        // sub-paths, must be registered before the {method} wildcard below.
        Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
        Route::get('/bank-accounts/{bankAccount}/edit', [BankAccountController::class, 'edit'])->name('bank-accounts.edit');
        Route::put('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
        Route::delete('/bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

        // Payment Restrictions — the standalone index/create/edit pages have
        // been absorbed into the payment method detail page's Availability
        // tab (Task 5 of the admin-v2 sales-channels plan); their views are
        // deleted, so the GET pages now 302 into the corresponding payments
        // page. The resource POST/PUT/DELETE and toggle endpoints are
        // unchanged and still power the Availability form.
        Route::get('/restrictions', [PaymentRestrictionController::class, 'index'])->name('payment-restrictions.index');
        Route::get('/restrictions/create', [PaymentRestrictionController::class, 'create'])->name('payment-restrictions.create');
        Route::get('/restrictions/{paymentRestriction}/edit', [PaymentRestrictionController::class, 'edit'])->name('payment-restrictions.edit');
        Route::post('/restrictions', [PaymentRestrictionController::class, 'store'])->name('payment-restrictions.store');
        Route::put('/restrictions/{paymentRestriction}', [PaymentRestrictionController::class, 'update'])->name('payment-restrictions.update');
        Route::delete('/restrictions/{paymentRestriction}', [PaymentRestrictionController::class, 'destroy'])->name('payment-restrictions.destroy');
        Route::post('/restrictions/{paymentRestriction}/toggle', [PaymentRestrictionController::class, 'toggleActive'])->name('payment-restrictions.toggle');

        // Method detail page — must stay last: a single-segment wildcard
        // that would otherwise swallow /bank-accounts and /restrictions.
        Route::get('/{method}', [PaymentMethodPageController::class, 'show'])->name('payment-settings.show');
    });

    // Shipping
    Route::prefix('shipping')->group(function () {
        Route::get('/', [ShippingSettingController::class, 'index'])->name('shipping-settings.index');
        Route::post('/test', ShippingTestController::class)->name('shipping-settings.test');

        // Per-provider configuration pages (each provider in the
        // ShippingQuoteService chain gets its own page; the index above is
        // now a provider list only). Literal, so no conflict with the
        // deeper '/free-rules', '/flat-rates/*', '/weight-based/*' child
        // CRUD routes registered below.
        Route::get('/free-shipping', [ShippingSettingController::class, 'freeShipping'])->name('shipping-settings.free-shipping');
        Route::get('/weight-based', [ShippingSettingController::class, 'weightBased'])->name('shipping-settings.weight-based');
        Route::get('/flat-rate', [ShippingSettingController::class, 'flatRate'])->name('shipping-settings.flat-rate');
        Route::get('/dhl', [ShippingSettingController::class, 'dhl'])->name('shipping-settings.dhl');

        // Route name stays 'free-shipping-rules.*' (frozen); URI segment is
        // 'free-rules' per the grammar map.
        Route::resource('free-rules', FreeShippingRuleController::class)
            ->except(['show'])
            ->parameters(['free-rules' => 'freeShippingRule'])
            ->names('free-shipping-rules');
        Route::post('/free-rules/{freeShippingRule}/toggle', [FreeShippingRuleController::class, 'toggleActive'])->name('free-shipping-rules.toggle');

        Route::get('/flat-rates/create', [FlatRateController::class, 'create'])->name('flat-rates.create');
        Route::post('/flat-rates', [FlatRateController::class, 'store'])->name('flat-rates.store');
        Route::get('/flat-rates/{flatRate}/edit', [FlatRateController::class, 'edit'])->name('flat-rates.edit');
        Route::put('/flat-rates/{flatRate}', [FlatRateController::class, 'update'])->name('flat-rates.update');
        Route::delete('/flat-rates/{flatRate}', [FlatRateController::class, 'destroy'])->name('flat-rates.destroy');

        // Route name stays 'weight-based-methods.*' (frozen); URI segment is
        // 'weight-based' per the grammar map. 'bulk' must precede the
        // {weightBasedMethod} PUT route below it, exactly as in the
        // pre-split file, or it would be captured as an id.
        Route::get('/weight-based/create', [WeightBasedMethodController::class, 'create'])->name('weight-based-methods.create');
        Route::post('/weight-based', [WeightBasedMethodController::class, 'store'])->name('weight-based-methods.store');
        Route::put('/weight-based/bulk', [WeightBasedMethodController::class, 'bulkUpdate'])->name('weight-based-methods.bulk-update');
        Route::get('/weight-based/{weightBasedMethod}/edit', [WeightBasedMethodController::class, 'edit'])->name('weight-based-methods.edit');
        Route::put('/weight-based/{weightBasedMethod}', [WeightBasedMethodController::class, 'update'])->name('weight-based-methods.update');
        Route::post('/weight-based/{weightBasedMethod}/duplicate', [WeightBasedMethodController::class, 'duplicate'])->name('weight-based-methods.duplicate');
        Route::delete('/weight-based/{weightBasedMethod}', [WeightBasedMethodController::class, 'destroy'])->name('weight-based-methods.destroy');
    });

    // Modules — extension manager (install/enable lifecycle UI)
    Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::post('/modules/{name}/install', [ModuleController::class, 'install'])->name('modules.install');
    Route::post('/modules/{name}/enable', [ModuleController::class, 'enable'])->name('modules.enable');
    Route::post('/modules/{name}/disable', [ModuleController::class, 'disable'])->name('modules.disable');
    Route::post('/modules/{name}/uninstall', [ModuleController::class, 'uninstall'])->name('modules.uninstall');

    // Abandoned Carts — configuration (rules, templates, settings). This is
    // a core feature (not an uninstallable extension), so it adopts the
    // settings/modules/{name} URL *convention* only, without a manifest.
    // Operational pages live at sales/abandoned-carts — see sales.php.
    Route::prefix('modules/abandoned-carts')->group(function () {
        Route::get('/rules', [AbandonedCartController::class, 'rules'])->name('abandoned-carts.rules');
        Route::get('/rules/create', [AbandonedCartController::class, 'createRule'])->name('abandoned-carts.rules.create');
        Route::post('/rules', [AbandonedCartController::class, 'storeRule'])->name('abandoned-carts.rules.store');
        Route::get('/rules/{rule}/edit', [AbandonedCartController::class, 'editRule'])->name('abandoned-carts.rules.edit');
        Route::put('/rules/{rule}', [AbandonedCartController::class, 'updateRule'])->name('abandoned-carts.rules.update');
        Route::delete('/rules/{rule}', [AbandonedCartController::class, 'destroyRule'])->name('abandoned-carts.rules.destroy');

        Route::get('/templates', [AbandonedCartController::class, 'templates'])->name('abandoned-carts.templates');
        Route::get('/templates/create', [AbandonedCartController::class, 'createTemplate'])->name('abandoned-carts.templates.create');
        Route::post('/templates', [AbandonedCartController::class, 'storeTemplate'])->name('abandoned-carts.templates.store');
        Route::get('/templates/{emailTemplate}/edit', [AbandonedCartController::class, 'editTemplate'])->name('abandoned-carts.templates.edit');
        Route::put('/templates/{emailTemplate}', [AbandonedCartController::class, 'updateTemplate'])->name('abandoned-carts.templates.update');
        Route::post('/templates/{emailTemplate}/toggle', [AbandonedCartController::class, 'toggleTemplate'])->name('abandoned-carts.templates.toggle');
        Route::post('/templates/{emailTemplate}/test', [AbandonedCartController::class, 'testTemplate'])->name('abandoned-carts.templates.test');
        Route::delete('/templates/{emailTemplate}', [AbandonedCartController::class, 'destroyTemplate'])->name('abandoned-carts.templates.destroy');

        Route::get('/settings', [AbandonedCartController::class, 'settings'])->name('abandoned-carts.settings');
        Route::post('/settings', [AbandonedCartController::class, 'updateSettings'])->name('abandoned-carts.settings.update');
    });

    // SEO engine config (Claude API key, generation rules, GSC credentials).
    // Same mount-convention-only treatment as abandoned-carts above. SEO
    // *workspace* (daily work) lives at marketing/seo — see marketing.php.
    // Route names keep the 'extensions.seo.' prefix (frozen).
    Route::prefix('modules/seo')->name('extensions.seo.')->group(function () {
        Route::get('/settings', [SeoExtensionController::class, 'settings'])->name('settings');
        Route::post('/settings', [SeoExtensionController::class, 'saveSettings'])->name('save-settings');
        Route::post('/settings/upload-gsc', [SeoExtensionController::class, 'uploadGsc'])->name('upload-gsc');
        Route::post('/settings/test-claude', [SeoExtensionController::class, 'testClaude'])->name('test-claude');
        Route::post('/settings/test-gsc', [SeoExtensionController::class, 'testGsc'])->name('test-gsc');
    });

    // Key-value settings pages (general, mail, checkout, currencies, api,
    // security, automations). MUST be registered last in this file: it's a
    // single-segment wildcard that would otherwise swallow every literal
    // settings/* route registered above it (payments, shipping, modules,
    // etc).
    Route::get('/{section}', [SettingsSectionController::class, 'show'])->name('settings.section');
});
