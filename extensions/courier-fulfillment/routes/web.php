<?php

use Extensions\CourierFulfillment\Http\Controllers\CourierSettingsController;
use Extensions\CourierFulfillment\Http\Controllers\CourierWebhookController;
use Extensions\CourierFulfillment\Http\Controllers\FulfillmentController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Admin: the operational Fulfillment queue — lives under Sales in the URL
// grammar. Route names are frozen at 'admin.fulfillment.*' (Task 1 already
// shipped code depending on them); only the URI root moved. The
// 'fulfillment_enabled' pause toggle is handled inside
// FulfillmentController@index itself (renders an explanatory empty state),
// not as route middleware — a redirect-away trap was rejected in review.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/sales/fulfillment')
    ->name('admin.fulfillment.')
    ->group(function () {
        Route::get('/', [FulfillmentController::class, 'index'])->name('index');
        Route::post('/{order}/book', [FulfillmentController::class, 'book'])->name('book');
        Route::post('/{order}/cancel', [FulfillmentController::class, 'cancel'])->name('cancel');
        Route::get('/{order}/label', [FulfillmentController::class, 'label'])->name('label');
        Route::post('/{order}/track', [FulfillmentController::class, 'track'])->name('track');
    });

// Admin: courier settings — mounted under the kernel's Settings > Modules
// convention (same convention banners/abandoned-carts/seo use). A SEPARATE
// route group from the queue above (different URI root) but sharing the
// same 'admin.fulfillment.' name prefix — names are frozen, only the two
// URI roots differ per the grammar's section-based mount.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/settings/modules/courier-fulfillment')
    ->name('admin.fulfillment.')
    ->group(function () {
        Route::get('/', [CourierSettingsController::class, 'index'])->name('settings');
        Route::post('/', [CourierSettingsController::class, 'update'])->name('settings.update');
        Route::post('/test-connection', [CourierSettingsController::class, 'testConnection'])->name('test-connection');
        Route::post('/refresh-statuses', [CourierSettingsController::class, 'refreshStatuses'])->name('settings.refresh-statuses');
    });

// Public webhook receiver — per-store token guarded, CSRF-exempt (external POST).
Route::post('/webhooks/courier/{provider}', [CourierWebhookController::class, 'handle'])
    ->middleware('web')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('courier.webhook');
