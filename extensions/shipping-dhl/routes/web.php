<?php

use Extensions\ShippingDhl\Http\Controllers\DhlController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Route name frozen (admin.shipping-settings.dhl). Credentials save
// through the core settings endpoint (admin.settings.update, group
// shipping) exactly as before.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/shipping')
    ->name('admin.')
    ->group(function () {
        Route::get('/dhl', [DhlController::class, 'page'])->name('shipping-settings.dhl');
    });
