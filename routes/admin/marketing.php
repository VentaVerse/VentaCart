<?php

use App\Http\Controllers\Admin\Marketing\CouponController;
use Illuminate\Support\Facades\Route;

Route::prefix('marketing')->group(function () {
    // Coupons
    Route::resource('coupons', CouponController::class)->except(['show']);
    Route::get('/coupons/search/products', [CouponController::class, 'searchProducts'])->name('coupons.search-products');
    Route::get('/coupons/search/categories', [CouponController::class, 'searchCategories'])->name('coupons.search-categories');
    Route::get('/coupons/search/brands', [CouponController::class, 'searchBrands'])->name('coupons.search-brands');

    // SEO moved to the seo extension (extensions/seo/routes/web.php).
});
