<?php

use Extensions\ErpSync\Http\Controllers\ErpCatalogController;
use Extensions\ErpSync\Http\Controllers\ErpOrderController;
use Extensions\ErpSync\Http\Controllers\ErpPricingController;
use Extensions\ErpSync\Http\Controllers\ErpProductController;
use Extensions\ErpSync\Http\Controllers\ErpVariantController;
use Illuminate\Support\Facades\Route;

// URLs frozen; the ERP pushes to these exact paths. Same guard as before
// the move.
Route::prefix('api/v1')->middleware(['api', 'erp.auth'])->group(function () {
    // Catalog (read-only lookups)
    Route::get('/categories', [ErpCatalogController::class, 'categories']);
    Route::get('/brands', [ErpCatalogController::class, 'brands']);
    Route::get('/order-statuses', [ErpCatalogController::class, 'orderStatuses']);
    Route::get('/shipping-couriers', [ErpCatalogController::class, 'shippingCouriers']);
    Route::get('/customer-groups', [ErpCatalogController::class, 'customerGroups']);

    // Products
    Route::get('/products', [ErpProductController::class, 'index']);
    Route::get('/products/{sku}', [ErpProductController::class, 'show']);
    Route::post('/products', [ErpProductController::class, 'store']);
    Route::put('/products/{sku}', [ErpProductController::class, 'update']);
    Route::delete('/products/{sku}', [ErpProductController::class, 'destroy']);
    Route::put('/products/{sku}/qty', [ErpProductController::class, 'updateQuantity']);
    Route::post('/products/{sku}/images', [ErpProductController::class, 'uploadImages']);

    // Special prices and discounts. A PUT replaces the whole list.
    Route::get('/products/{sku}/specials', [ErpPricingController::class, 'specials']);
    Route::put('/products/{sku}/specials', [ErpPricingController::class, 'replaceSpecials']);
    Route::get('/products/{sku}/discounts', [ErpPricingController::class, 'discounts']);
    Route::put('/products/{sku}/discounts', [ErpPricingController::class, 'replaceDiscounts']);

    // Variants
    Route::get('/products/{sku}/variants', [ErpVariantController::class, 'index']);
    Route::get('/variants/{sku}', [ErpVariantController::class, 'show']);
    Route::put('/variants/{sku}', [ErpVariantController::class, 'update']);
    Route::post('/products/{sku}/variants', [ErpVariantController::class, 'store']);

    // Orders
    Route::get('/orders', [ErpOrderController::class, 'index']);
    Route::get('/orders/{id}', [ErpOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [ErpOrderController::class, 'updateStatus']);
});
