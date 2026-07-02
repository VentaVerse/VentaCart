<?php

use Extensions\CourierFulfillment\Http\Controllers\CourierSettingsController;
use Extensions\CourierFulfillment\Http\Controllers\CourierWebhookController;
use Extensions\CourierFulfillment\Http\Controllers\FulfillmentController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Admin: the dedicated Fulfillment page (respects the configurable admin prefix).
Route::middleware(['web', 'auth'])
    ->prefix($adminPrefix . '/fulfillment')
    ->name('admin.fulfillment.')
    ->group(function () {
        Route::get('/', [FulfillmentController::class, 'index'])->name('index');
        Route::post('/{order}/book', [FulfillmentController::class, 'book'])->name('book');
        Route::post('/{order}/cancel', [FulfillmentController::class, 'cancel'])->name('cancel');
        Route::get('/{order}/label', [FulfillmentController::class, 'label'])->name('label');
        Route::post('/{order}/track', [FulfillmentController::class, 'track'])->name('track');

        Route::get('/settings', [CourierSettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [CourierSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-connection', [CourierSettingsController::class, 'testConnection'])->name('test-connection');
    });

// Public webhook receiver — per-store token guarded, CSRF-exempt (external POST).
Route::post('/webhooks/courier/{provider}', [CourierWebhookController::class, 'handle'])
    ->middleware('web')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('courier.webhook');
