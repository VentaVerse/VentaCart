<?php

use Extensions\ShippingFlatRate\Http\Controllers\FlatRateController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Route names frozen (admin.shipping-settings.flat-rate, admin.flat-rates.*).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/shipping')
    ->name('admin.')
    ->group(function () {
        Route::get('/flat-rate', [FlatRateController::class, 'page'])->name('shipping-settings.flat-rate');

        Route::get('/flat-rates/create', [FlatRateController::class, 'create'])->name('flat-rates.create');
        Route::post('/flat-rates', [FlatRateController::class, 'store'])->name('flat-rates.store');
        Route::get('/flat-rates/{flatRate}/edit', [FlatRateController::class, 'edit'])->name('flat-rates.edit');
        Route::put('/flat-rates/{flatRate}', [FlatRateController::class, 'update'])->name('flat-rates.update');
        Route::delete('/flat-rates/{flatRate}', [FlatRateController::class, 'destroy'])->name('flat-rates.destroy');
    });
