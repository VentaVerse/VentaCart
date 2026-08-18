<?php

use Extensions\StoreCredit\Http\Controllers\StoreCreditController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home: the ledger and its adjustment endpoint. No work-nav entry
// (owner ruling: credit lives on the customer's page and at checkout;
// the ledger is the extension's configure page).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/store-credit')
    ->name('admin.store-credit.')
    ->group(function () {
        Route::get('/', [StoreCreditController::class, 'index'])->name('index');
        Route::post('/adjust', [StoreCreditController::class, 'adjust'])->name('adjust');
    });
