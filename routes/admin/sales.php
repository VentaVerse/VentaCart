<?php

use App\Http\Controllers\Admin\Sales\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('sales')->group(function () {
    // Orders
    Route::get('/orders/search-products', [OrderController::class, 'searchProducts'])->name('orders.search-products');
    Route::get('/orders/search-customers', [OrderController::class, 'searchCustomers'])->name('orders.search-customers');
    Route::post('/orders/available-shipping', [OrderController::class, 'availableShipping'])->name('orders.available-shipping');
    Route::post('/orders/available-payments', [OrderController::class, 'availablePayments'])->name('orders.available-payments');
    Route::post('/orders/validate-coupon', [OrderController::class, 'validateCoupon'])->name('orders.validate-coupon');
    Route::post('/orders/{order}/history', [OrderController::class, 'addHistory'])->name('orders.history.store');
    Route::delete('/orders/{order}/history/{history}', [OrderController::class, 'deleteHistory'])->name('orders.history.destroy');
    Route::post('/orders/{order}/tracking', [OrderController::class, 'updateTracking'])->name('orders.update-tracking');
    Route::resource('orders', OrderController::class);

    // Abandoned Carts moved to the abandoned-carts extension
    // (extensions/abandoned-carts/routes/web.php).

    // Reviews moved to the reviews extension
    // (extensions/reviews/routes/web.php).
});
