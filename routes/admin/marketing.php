<?php

use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\Extensions\SeoExtensionController;
use App\Http\Controllers\Admin\SeoNotFoundController;
use App\Http\Controllers\Admin\SeoRedirectController;
use Illuminate\Support\Facades\Route;

Route::prefix('marketing')->group(function () {
    // Coupons
    Route::resource('coupons', CouponController::class)->except(['show']);
    Route::get('/coupons/search/products', [CouponController::class, 'searchProducts'])->name('coupons.search-products');
    Route::get('/coupons/search/categories', [CouponController::class, 'searchCategories'])->name('coupons.search-categories');
    Route::get('/coupons/search/brands', [CouponController::class, 'searchBrands'])->name('coupons.search-brands');

    // SEO workspace — daily work: dashboard, review, slugs, entity lists,
    // search console, redirect manager, 404 monitor, strategy. SEO *engine
    // config* (Claude API key, generation rules, GSC credentials) lives at
    // settings/modules/seo — see settings.php. Route names keep the
    // 'extensions.seo.' prefix (frozen) even though the workspace/config
    // split now spans two files.
    Route::prefix('seo')->name('extensions.seo.')->group(function () {
        Route::get('/', [SeoExtensionController::class, 'dashboard'])->name('dashboard');
        Route::get('/search-console', [SeoExtensionController::class, 'searchConsole'])->name('search-console');
        Route::get('/strategy', [SeoExtensionController::class, 'strategy'])->name('strategy');
        Route::get('/review', [SeoExtensionController::class, 'review'])->name('review');
        Route::get('/slugs', [SeoExtensionController::class, 'slugs'])->name('slugs');
        Route::post('/slugs/update', [SeoExtensionController::class, 'updateSlug'])->name('update-slug');
        Route::post('/strategy', [SeoExtensionController::class, 'saveStrategy'])->name('save-strategy');
        Route::post('/generate', [SeoExtensionController::class, 'generate'])->name('generate');
        Route::post('/optimize', [SeoExtensionController::class, 'optimize'])->name('optimize');
        Route::post('/suggestions/{suggestion}/accept', [SeoExtensionController::class, 'accept'])->name('accept');
        Route::post('/suggestions/{suggestion}/reject', [SeoExtensionController::class, 'reject'])->name('reject');
        Route::post('/suggestions/bulk-accept', [SeoExtensionController::class, 'bulkAccept'])->name('bulk-accept');

        // Redirect manager — admin-managed source→destination 301/302/410 rules
        Route::get('/redirects', [SeoRedirectController::class, 'index'])->name('redirects.index');
        Route::post('/redirects', [SeoRedirectController::class, 'store'])->name('redirects.store');
        Route::put('/redirects/{redirect}', [SeoRedirectController::class, 'update'])->name('redirects.update');
        Route::delete('/redirects/{redirect}', [SeoRedirectController::class, 'destroy'])->name('redirects.destroy');

        // 404 monitor — recently-seen missing URLs with quick-redirect action
        Route::get('/not-found', [SeoNotFoundController::class, 'index'])->name('not-found.index');
        Route::delete('/not-found/{log}', [SeoNotFoundController::class, 'destroy'])->name('not-found.destroy');
        Route::post('/not-found/clear', [SeoNotFoundController::class, 'clear'])->name('not-found.clear');

        // Wildcard entity listing — must stay last: it would otherwise
        // swallow the literal siblings above it.
        Route::get('/{type}', [SeoExtensionController::class, 'entities'])->name('entities')->where('type', 'products|categories|brands|blog|pages');
    });
});
