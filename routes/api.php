<?php

use App\Http\Controllers\Api\ErpProductController;
use App\Http\Controllers\Api\ErpVariantController;
use App\Http\Controllers\Api\ErpOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('erp')->middleware('erp.auth')->group(function () {
    // Products
    Route::get('/products', [ErpProductController::class, 'index']);
    Route::get('/products/{sku}', [ErpProductController::class, 'show']);
    Route::post('/products', [ErpProductController::class, 'store']);
    Route::put('/products/{sku}', [ErpProductController::class, 'update']);
    Route::post('/products/{sku}/images', [ErpProductController::class, 'uploadImages']);

    // Variants
    Route::get('/variants/{sku}', [ErpVariantController::class, 'show']);
    Route::put('/variants/{sku}', [ErpVariantController::class, 'update']);

    // Orders
    Route::get('/orders', [ErpOrderController::class, 'index']);
    Route::get('/orders/{id}', [ErpOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [ErpOrderController::class, 'updateStatus']);
});
