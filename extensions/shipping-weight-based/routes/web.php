<?php

use Extensions\ShippingWeightBased\Http\Controllers\WeightBasedMethodController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Route names frozen (admin.shipping-settings.weight-based,
// admin.weight-based-methods.*). 'bulk' must precede the
// {weightBasedMethod} PUT below it or it would be captured as an id.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/shipping')
    ->name('admin.')
    ->group(function () {
        Route::get('/weight-based', [WeightBasedMethodController::class, 'page'])->name('shipping-settings.weight-based');

        Route::get('/weight-based/create', [WeightBasedMethodController::class, 'create'])->name('weight-based-methods.create');
        Route::post('/weight-based', [WeightBasedMethodController::class, 'store'])->name('weight-based-methods.store');
        Route::put('/weight-based/bulk', [WeightBasedMethodController::class, 'bulkUpdate'])->name('weight-based-methods.bulk-update');
        Route::get('/weight-based/{weightBasedMethod}/edit', [WeightBasedMethodController::class, 'edit'])->name('weight-based-methods.edit');
        Route::put('/weight-based/{weightBasedMethod}', [WeightBasedMethodController::class, 'update'])->name('weight-based-methods.update');
        Route::post('/weight-based/{weightBasedMethod}/duplicate', [WeightBasedMethodController::class, 'duplicate'])->name('weight-based-methods.duplicate');
        Route::delete('/weight-based/{weightBasedMethod}', [WeightBasedMethodController::class, 'destroy'])->name('weight-based-methods.destroy');
    });
