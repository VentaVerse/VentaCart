<?php

use App\Http\Controllers\Admin\Sales\AbandonedCartController;
use App\Http\Controllers\Admin\Sales\OrderController;
use App\Http\Controllers\Admin\Sales\ReviewController as AdminReviewController;
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

    // Abandoned Carts — operational only (list, show, notifications,
    // recovered). Configuration (rules, templates, settings) lives at
    // settings/modules/abandoned-carts — see settings.php.
    Route::get('/abandoned-carts', [AbandonedCartController::class, 'index'])->name('abandoned-carts.index');
    Route::get('/abandoned-carts/notifications', [AbandonedCartController::class, 'notifications'])->name('abandoned-carts.notifications');
    Route::get('/abandoned-carts/recovered', [AbandonedCartController::class, 'recovered'])->name('abandoned-carts.recovered');
    Route::get('/abandoned-carts/{cart}', [AbandonedCartController::class, 'show'])->name('abandoned-carts.show');
    Route::delete('/abandoned-carts/{cart}', [AbandonedCartController::class, 'destroy'])->name('abandoned-carts.destroy');
    Route::post('/abandoned-carts/mass-delete', [AbandonedCartController::class, 'massDelete'])->name('abandoned-carts.mass-delete');

    // Reviews
    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/{review}', [AdminReviewController::class, 'show'])->name('reviews.show');
    Route::post('/reviews/{review}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('/reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');
});
