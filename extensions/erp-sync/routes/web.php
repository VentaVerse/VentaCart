<?php

use Extensions\ErpSync\Http\Controllers\ErpSyncSettingsController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// The extension's own configuration page, behind the Extensions door
// like every other module's (owner ruling: module config never lives
// in Settings).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/erp-sync')
    ->name('admin.erp-sync.')
    ->group(function () {
        Route::get('/', [ErpSyncSettingsController::class, 'page'])->name('settings');
        Route::post('/keys', [ErpSyncSettingsController::class, 'store'])->name('keys.store');
        Route::delete('/keys/{apiKey}', [ErpSyncSettingsController::class, 'destroy'])->name('keys.destroy');
    });
