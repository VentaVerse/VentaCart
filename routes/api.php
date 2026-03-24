<?php

use App\Http\Controllers\Api\ErpProductController;
use App\Http\Controllers\Api\ErpVariantController;
use App\Http\Controllers\Api\ErpOrderController;
use App\Http\Controllers\Api\ErpCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('erp.auth')->group(function () {
    // Catalog (read-only lookups)
    Route::get('/categories', [ErpCatalogController::class, 'categories']);
    Route::get('/brands', [ErpCatalogController::class, 'brands']);
    Route::get('/order-statuses', [ErpCatalogController::class, 'orderStatuses']);

    // Products
    Route::get('/products', [ErpProductController::class, 'index']);
    Route::get('/products/{sku}', [ErpProductController::class, 'show']);
    Route::post('/products', [ErpProductController::class, 'store']);
    Route::put('/products/{sku}', [ErpProductController::class, 'update']);
    Route::put('/products/{sku}/qty', [ErpProductController::class, 'updateQuantity']);
    Route::post('/products/{sku}/images', [ErpProductController::class, 'uploadImages']);

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
