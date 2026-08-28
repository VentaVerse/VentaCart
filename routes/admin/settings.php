<?php

use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\Settings\ApiSyncLogController;
use App\Http\Controllers\Admin\Settings\CurrencyController;
use App\Http\Controllers\Admin\Settings\EmailTemplateController;
use App\Http\Controllers\Admin\Settings\LocalizationController;
use App\Http\Controllers\Admin\Settings\OrderStatusController;
use App\Http\Controllers\Admin\Settings\SettingController;
use App\Http\Controllers\Admin\Settings\SettingsHubController;
use App\Http\Controllers\Admin\Settings\SettingsSectionController;
use App\Http\Controllers\Admin\Settings\StoreIdentityController;
use App\Http\Controllers\Admin\Settings\UserController;
use App\Http\Controllers\Admin\Settings\UserGroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->group(function () {
    Route::get('/', [SettingsHubController::class, 'index'])->name('settings.index');
    Route::post('/', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/test-mail', [SettingController::class, 'testMail'])->name('settings.test-mail');
    Route::post('/reveal-secret', [SettingController::class, 'revealSecret'])->name('settings.reveal-secret');
    Route::post('/test-dhl', [SettingController::class, 'testDhl'])->name('settings.test-dhl');
    Route::post('/generate-erp-token', [SettingController::class, 'generateErpToken'])->name('settings.generate-erp-token');
    Route::post('/upload-gsc-credentials', [SettingController::class, 'uploadGscCredentials'])->name('settings.upload-gsc-credentials');

    // The store's name and marks. Its own endpoint rather than the shared
    // key-value one because the marks are files: they need validation the
    // generic setter has no business carrying, and replacing one has to
    // delete the file it replaced.
    Route::post('/identity', [StoreIdentityController::class, 'update'])->name('settings.identity.update');
    Route::post('/identity/{asset}/remove', [StoreIdentityController::class, 'destroy'])->name('settings.identity.remove');
    Route::delete('/identity/{asset}', [StoreIdentityController::class, 'destroy'])
        ->whereIn('asset', ['logo', 'favicon'])
        ->name('settings.identity.destroy');

    // Automations (Settings > Automations section — DB-managed scheduled
    // tasks). AutomationController::index is a legacy stub that redirects
    // into the old tabbed settings page; it is nested one level deeper
    // than the section's own GET (settings/automations, handled by the
    // settings/{section} catch-all below) so the two don't collide.
    Route::get('/automations/index', [AutomationController::class, 'index'])->name('automations.index');
    Route::put('/automations', [AutomationController::class, 'updateAll'])->name('automations.update-all');
    Route::patch('/automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::post('/automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');
    Route::post('/automations/{automation}/run', [AutomationController::class, 'runNow'])->name('automations.runNow');

    // API Sync Logs
    Route::get('/api-logs', [ApiSyncLogController::class, 'index'])->name('api-sync-logs.index');
    Route::delete('/api-logs', [ApiSyncLogController::class, 'destroyAll'])->name('api-sync-logs.destroy-all');

    // Order Statuses
    Route::get('/order-statuses', [OrderStatusController::class, 'index'])->name('order-statuses.index');
    Route::post('/order-statuses', [OrderStatusController::class, 'store'])->name('order-statuses.store');
    Route::put('/order-statuses', [OrderStatusController::class, 'updateAll'])->name('order-statuses.update-all');
    Route::put('/order-statuses/{orderStatus}', [OrderStatusController::class, 'update'])->name('order-statuses.update');
    Route::delete('/order-statuses/{orderStatus}', [OrderStatusController::class, 'destroy'])->name('order-statuses.destroy');

    // Email Templates
    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('/email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('/email-templates', [EmailTemplateController::class, 'updateAll'])->name('email-templates.update-all');
    Route::put('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::post('/email-templates/{emailTemplate}/toggle', [EmailTemplateController::class, 'toggleActive'])->name('email-templates.toggle');
    Route::post('/email-templates/{emailTemplate}/test', [EmailTemplateController::class, 'sendTest'])->name('email-templates.send-test');

    // Localization
    Route::get('/localization', [LocalizationController::class, 'index'])->name('localization.index');
    Route::put('/localization/countries', [LocalizationController::class, 'updateAllCountries'])->name('localization.update-all-countries');
    Route::put('/localization/zones-bulk', [LocalizationController::class, 'updateAllZones'])->name('localization.update-all-zones');
    Route::put('/localization/geo-zones-bulk', [LocalizationController::class, 'updateAllGeoZones'])->name('localization.update-all-geo-zones');
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

    // User Groups (OpenCart-style: each group carries its own
    // permission config; replaced the all-groups Permissions matrix)
    Route::resource('user-groups', UserGroupController::class)->except(['show']);
    Route::post('user-groups/{user_group}/duplicate', [UserGroupController::class, 'duplicate'])->name('user-groups.duplicate');

    // Theme editor moved to the theme extension
    // (extensions/theme/routes/web.php). Site currencies stay core —
    // see /currencies below (their admin.theme.currencies.* names are
    // frozen history, not a theme dependency).

    // Currencies (Settings > Store > Currencies section page's backing CRUD;
    // the page itself renders through the settings/{section} catch-all
    // below — there is deliberately no literal GET '/' here)
    Route::prefix('currencies')->group(function () {
        Route::post('/', [CurrencyController::class, 'store'])->name('theme.currencies.store');
        Route::put('/', [CurrencyController::class, 'updateAll'])->name('theme.currencies.update-all');
        Route::put('/{currency}', [CurrencyController::class, 'update'])->name('theme.currencies.update');
        Route::delete('/{currency}', [CurrencyController::class, 'destroy'])->name('theme.currencies.destroy');
        Route::post('/fetch-rates', [CurrencyController::class, 'fetchRates'])->name('theme.currencies.fetch-rates');
    });

    // Payments

    // Shipping




    // Key-value settings pages (general, mail, checkout, currencies, api,
    // security, automations). MUST be registered last in this file: it's a
    // Moved subtrees (wave 0): their roots must 301 to the Extensions
    // area BEFORE the {section} wildcard below swallows them. The
    // path-preserving deep redirects live in redirects.php; these two
    // cover the bare roots the wildcard would otherwise claim.
    Route::get('/payments', fn () => redirect(route('admin.payment-settings.index'), 301));
    Route::get('/shipping', fn () => redirect(route('admin.shipping-settings.index'), 301));
    Route::get('/modules', fn () => redirect(route('admin.extensions.index'), 301));
    Route::get('/stock-alerts', fn () => redirect(route('admin.stock-notifications.index'), 301));
    Route::get('/theme', fn () => redirect(route('admin.theme.index'), 301));
    Route::get('/api', fn () => redirect(route('admin.erp-sync.settings'), 301));

    // single-segment wildcard that would otherwise swallow every literal
    // settings/* route registered above it (payments, shipping, etc).
    // Couriers lived here until 2026-08-28 and now answers under Sales. Its
    // three page URLs are redirected HERE rather than in redirects.php,
    // because that file loads after this one and the {section} catch-all
    // below would have swallowed them first (a 404 from the section
    // controller, never the redirect). Literal siblings before the wildcard,
    // same rule as the block above.
    foreach ([
        'couriers/create'                  => ['admin.shipping-couriers.create', []],
        'couriers/{shippingCourier}/edit'  => ['admin.shipping-couriers.edit', ['shippingCourier']],
        'couriers'                         => ['admin.shipping-couriers.index', []],
    ] as $old => [$name, $params]) {
        Route::get($old, function (...$args) use ($name) {
            $url = route($name, $args);
            if ($qs = request()->getQueryString()) {
                $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
            }

            return redirect($url, 301);
        });
    }

    Route::get('/{section}', [SettingsSectionController::class, 'show'])->name('settings.section');
});
