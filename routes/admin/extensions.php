<?php

use App\Http\Controllers\Admin\ExtensionsController;
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
    // The Order Totals category ('order-totals' is reserved the same way):
    // the lines between subtotal and total — coupons, store credit,
    // future rewards/fees. The mandatory arithmetic (subtotal, shipping,
    // total) is core and deliberately NOT listed here: OpenCart let you
    // uninstall Sub-Total and break every checkout; Venta does not.
    Route::get('/order-totals', [ExtensionsController::class, 'orderTotals'])->name('extensions.order-totals');
    Route::post('/{name}/install', [ExtensionsController::class, 'install'])->name('extensions.install');
    Route::post('/{name}/enable', [ExtensionsController::class, 'enable'])->name('extensions.enable');
    Route::post('/{name}/disable', [ExtensionsController::class, 'disable'])->name('extensions.disable');
    Route::post('/{name}/uninstall', [ExtensionsController::class, 'uninstall'])->name('extensions.uninstall');

    // Abandoned Carts moved to the abandoned-carts extension
    // (extensions/abandoned-carts/routes/web.php).

    // SEO moved to the seo extension (extensions/seo/routes/web.php).

    // Payments — the payment-methods category page and everything it
    // owns (bank accounts, restrictions, per-method pages). Route names
    // frozen; only the mount moved (wave 0 piece 2b).
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentSettingController::class, 'index'])->name('payment-settings.index');


        // Bank accounts moved to the payment-bank-transfer extension
        // (extensions/payment-bank-transfer/routes/web.php).

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
