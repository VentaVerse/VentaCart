<?php

use App\Http\Controllers\Admin\Sales\OrderController;
use App\Http\Controllers\Admin\Sales\ShippingCourierController;
use Illuminate\Support\Facades\Route;

Route::prefix('sales')->group(function () {
    // Couriers: the directory of who carries orders and where a tracking
    // number links to. Order vocabulary, like statuses, and core: it works
    // with no fulfillment module installed. Route NAMES are frozen
    // (shipping-couriers.*); only the home moved, from Settings.
    Route::get('/couriers', [ShippingCourierController::class, 'index'])->name('shipping-couriers.index');
    Route::get('/couriers/create', [ShippingCourierController::class, 'create'])->name('shipping-couriers.create');
    Route::get('/couriers/{shippingCourier}/edit', [ShippingCourierController::class, 'edit'])->name('shipping-couriers.edit');
    Route::post('/couriers', [ShippingCourierController::class, 'store'])->name('shipping-couriers.store');
    Route::put('/couriers/{shippingCourier}', [ShippingCourierController::class, 'update'])->name('shipping-couriers.update');
    Route::delete('/couriers/{shippingCourier}', [ShippingCourierController::class, 'destroy'])->name('shipping-couriers.destroy');

    // Orders
    Route::get('/orders/search-products', [OrderController::class, 'searchProducts'])->name('orders.search-products');
    Route::get('/orders/search-customers', [OrderController::class, 'searchCustomers'])->name('orders.search-customers');
    Route::post('/orders/available-shipping', [OrderController::class, 'availableShipping'])->name('orders.available-shipping');
    Route::post('/orders/available-payments', [OrderController::class, 'availablePayments'])->name('orders.available-payments');
    Route::post('/orders/{order}/history', [OrderController::class, 'addHistory'])->name('orders.history.store');
    Route::delete('/orders/{order}/history/{history}', [OrderController::class, 'deleteHistory'])->name('orders.history.destroy');
    Route::post('/orders/{order}/tracking', [OrderController::class, 'updateTracking'])->name('orders.update-tracking');
    Route::resource('orders', OrderController::class);

    // Abandoned Carts moved to the abandoned-carts extension
    // (extensions/abandoned-carts/routes/web.php).

    // Reviews moved to the reviews extension
    // (extensions/reviews/routes/web.php).
});
