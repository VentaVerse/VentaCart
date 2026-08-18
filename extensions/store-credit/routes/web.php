<?php

use Extensions\StoreCredit\Http\Controllers\StoreCreditController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home. Configure lands on the thin settings page (OC parity for
// Order Totals extensions: enable/disable + checkout sort order); the
// ledger is the secondary page. Day-to-day granting lives on the
// customer's own page. No work-nav entry (owner ruling).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/store-credit')
    ->name('admin.store-credit.')
    ->group(function () {
        Route::get('/', [StoreCreditController::class, 'settings'])->name('index');
        Route::get('/ledger', [StoreCreditController::class, 'ledger'])->name('ledger');
        Route::post('/adjust', [StoreCreditController::class, 'adjust'])->name('adjust');
    });
