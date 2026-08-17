<?php

use Extensions\ShippingFree\Http\Controllers\FreeShippingRuleController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Route names frozen (admin.shipping-settings.free-shipping,
// admin.free-shipping-rules.*); only the owner moved with the conversion.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/shipping')
    ->name('admin.')
    ->group(function () {
        Route::get('/free-shipping', [FreeShippingRuleController::class, 'page'])->name('shipping-settings.free-shipping');

        Route::resource('free-rules', FreeShippingRuleController::class)
            ->except(['show'])
            ->parameters(['free-rules' => 'freeShippingRule'])
            ->names('free-shipping-rules');
        Route::post('/free-rules/{freeShippingRule}/toggle', [FreeShippingRuleController::class, 'toggleActive'])->name('free-shipping-rules.toggle');
    });
