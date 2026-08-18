<?php

use Extensions\Reviews\Http\Controllers\ErpReviewController;
use Illuminate\Support\Facades\Route;

// ERP push endpoint (imported platform reviews). Same guard and prefix
// as the core ERP API; URL frozen.
Route::prefix('api/v1')->middleware(['api', 'erp.auth'])->group(function () {
    Route::post('/reviews', [ErpReviewController::class, 'store']);
});
