<?php

use Extensions\Coupons\Http\Controllers\AdminCouponController;
use Extensions\Coupons\Http\Controllers\StorefrontCouponController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home: everything the coupons extension owns lives under
// extensions/coupons; the Marketing nav entry is a pointer in. Route
// names frozen from the marketing/coupons era. Search literals are
// registered before the {coupon} wildcard so it cannot swallow them.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/coupons')
    ->name('admin.coupons.')
    ->group(function () {
        Route::get('/search/products', [AdminCouponController::class, 'searchProducts'])->name('search-products');
        Route::get('/search/categories', [AdminCouponController::class, 'searchCategories'])->name('search-categories');
        Route::get('/search/brands', [AdminCouponController::class, 'searchBrands'])->name('search-brands');

        Route::get('/', [AdminCouponController::class, 'index'])->name('index');
        Route::get('/create', [AdminCouponController::class, 'create'])->name('create');
        Route::post('/', [AdminCouponController::class, 'store'])->name('store');
        Route::get('/{coupon}/edit', [AdminCouponController::class, 'edit'])->name('edit')->whereNumber('coupon');
        Route::put('/{coupon}', [AdminCouponController::class, 'update'])->name('update')->whereNumber('coupon');
        Route::delete('/{coupon}', [AdminCouponController::class, 'destroy'])->name('destroy')->whereNumber('coupon');
    });

// The order form's coupon check (name and URI frozen; the form's coupon
// box renders only when this route exists).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix)
    ->group(function () {
        Route::post('/orders/validate-coupon', [AdminCouponController::class, 'validateForOrder'])->name('admin.orders.validate-coupon');
    });

// Storefront: apply/remove on the checkout page (URIs frozen; same
// middleware posture as when these lived in routes/web.php).
Route::middleware(['web', 'customer.auth', 'noindex'])->group(function () {
    Route::post('/checkout/coupon/apply', [StorefrontCouponController::class, 'apply'])->name('checkout.coupon.apply');
    Route::delete('/checkout/coupon/remove', [StorefrontCouponController::class, 'remove'])->name('checkout.coupon.remove');
});
