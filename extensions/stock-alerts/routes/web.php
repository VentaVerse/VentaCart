<?php

use Extensions\StockAlerts\Http\Controllers\StockAlertsController;
use Extensions\StockAlerts\Http\Controllers\SubscribeController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Admin: the waitlist, notified list, and alert template — mounted under
// the extensions/{name} convention. Route names frozen
// (admin.stock-notifications.*), only the mount moved with the conversion.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/stock-alerts')
    ->name('admin.stock-notifications.')
    ->group(function () {
        Route::get('/', [StockAlertsController::class, 'index'])->name('index');
        Route::delete('/{stockNotification}', [StockAlertsController::class, 'destroy'])->name('destroy');
        Route::post('/bulk-delete', [StockAlertsController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('/template', [StockAlertsController::class, 'updateTemplate'])->name('update-template');
        Route::post('/test-email', [StockAlertsController::class, 'sendTest'])->name('send-test');
    });

// Storefront: the "notify me" subscribe endpoint. The theme posts to this
// literal URL (theme.js); disabled extension = 404, and the theme's own
// error handling reports the miss.
Route::middleware('web')
    ->post('/notify-stock', [SubscribeController::class, 'store'])
    ->name('notify-stock.store');
