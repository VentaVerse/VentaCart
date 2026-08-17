<?php

use App\Http\Controllers\Admin\ExtensionsController;
use App\Http\Controllers\Admin\Marketing\Seo\SeoExtensionController;
use App\Http\Controllers\Admin\Settings\BankAccountController;
use App\Http\Controllers\Admin\Settings\ShippingSettingController;
use App\Http\Controllers\Admin\Settings\ShippingTestController;
use App\Http\Controllers\Admin\Settings\PaymentMethodPageController;
use App\Http\Controllers\Admin\Settings\PaymentRestrictionController;
use App\Http\Controllers\Admin\Settings\PaymentSettingController;
use Illuminate\Support\Facades\Route;

/*
 * The Extensions area (extension architecture wave 0): the directory of
 * every packaged feature, the lifecycle endpoints, and the settings of
 * extensions mounted under the extensions/{name} URL convention. A
 * top-level area beside Settings, never inside it: Settings is the
 * website's core settings; this door is where optional capability lives.
 */
Route::prefix('extensions')->group(function () {
    // The directory + lifecycle (install/enable/disable; uninstall stays route-only)
    Route::get('/', [ExtensionsController::class, 'index'])->name('extensions.index');
    // The Modules category (literal, so it must never collide with an
    // extension mount: 'modules' is a reserved extension name).
    Route::get('/modules', [ExtensionsController::class, 'modules'])->name('extensions.modules');
    Route::post('/features/{feature}/enable', [ExtensionsController::class, 'featureEnable'])->name('extensions.feature-enable');
    Route::post('/features/{feature}/disable', [ExtensionsController::class, 'featureDisable'])->name('extensions.feature-disable');
    Route::post('/{name}/install', [ExtensionsController::class, 'install'])->name('extensions.install');
    Route::post('/{name}/enable', [ExtensionsController::class, 'enable'])->name('extensions.enable');
    Route::post('/{name}/disable', [ExtensionsController::class, 'disable'])->name('extensions.disable');
    Route::post('/{name}/uninstall', [ExtensionsController::class, 'uninstall'])->name('extensions.uninstall');

    // Abandoned Carts moved to the abandoned-carts extension
    // (extensions/abandoned-carts/routes/web.php).

    // SEO engine config (Claude API key, generation rules, GSC credentials).
    // Same mount-convention-only treatment as abandoned-carts above. SEO
    // *workspace* (daily work) lives at marketing/seo — see marketing.php.
    // Route names keep the 'extensions.seo.' prefix (frozen).
    Route::prefix('seo')->name('extensions.seo.')->group(function () {
        Route::get('/settings', [SeoExtensionController::class, 'settings'])->name('settings');
        Route::post('/settings', [SeoExtensionController::class, 'saveSettings'])->name('save-settings');
        Route::post('/settings/upload-gsc', [SeoExtensionController::class, 'uploadGsc'])->name('upload-gsc');
        Route::post('/settings/test-claude', [SeoExtensionController::class, 'testClaude'])->name('test-claude');
        Route::post('/settings/test-gsc', [SeoExtensionController::class, 'testGsc'])->name('test-gsc');
    });

    // Payments — the payment-methods category page and everything it
    // owns (bank accounts, restrictions, per-method pages). Route names
    // frozen; only the mount moved (wave 0 piece 2b).
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentSettingController::class, 'index'])->name('payment-settings.index');

        // The standard row switch. {family} is constrained to the known
        // families so these wildcards can never swallow a literal sibling.
        Route::post('/{family}/enable', [PaymentSettingController::class, 'enable'])
            ->whereIn('family', array_keys(\App\Support\PaymentFamilies::LIST))
            ->name('payment-settings.enable');
        Route::post('/{family}/disable', [PaymentSettingController::class, 'disable'])
            ->whereIn('family', array_keys(\App\Support\PaymentFamilies::LIST))
            ->name('payment-settings.disable');

        // Bank Accounts (bank-transfer method's account list) — literal
        // sub-paths, must be registered before the {method} wildcard below.
        Route::get('/bank-accounts/create', [BankAccountController::class, 'create'])->name('bank-accounts.create');
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


    // Shipping — the shipping-methods category page (rate methods and,
    // per the blessed draft, the couriers section) and everything it
    // owns. Route names frozen; only the mount moved (wave 0 piece 2c).
    Route::prefix('shipping')->group(function () {
        Route::get('/', [ShippingSettingController::class, 'index'])->name('shipping-settings.index');
        Route::post('/test', ShippingTestController::class)->name('shipping-settings.test');


        // Per-provider pages and CRUD moved into the four shipping method
        // extensions (extensions/shipping-*/routes/web.php). Route names
        // frozen there.
    });

});
