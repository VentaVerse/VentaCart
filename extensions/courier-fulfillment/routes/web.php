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
        // Command-bar action: run the courier:poll-status pull on demand.
        // Static segment registered BEFORE the {order} wildcards below so
        // 'poll' can never be captured as an order id.
        Route::post('/poll', [FulfillmentController::class, 'poll'])->name('poll');
        // Task W4: returns monitoring — a sibling PAGE (never an eighth queue
        // tab), listing orders whose spine phase is 'returning' or 'failed'.
        // Static segment, same reasoning as 'poll' above.
        Route::get('/returns', [FulfillmentController::class, 'returns'])->name('returns');
        Route::post('/{order}/book', [FulfillmentController::class, 'book'])->name('book');
        // Task T4: lazy, per-order/per-selection AJAX endpoints the booking
        // modal's step 2 hits on demand (SPX's estimate button / pickup-slot
        // fetch on courier choice) — never called during the queue's own
        // page load. See FulfillmentController::estimate()/pickupSlots()'s
        // docblocks for why these are lazy rather than baked into
        // index()'s courierOptions payload.
        Route::post('/{order}/estimate', [FulfillmentController::class, 'estimate'])->name('estimate');
        Route::get('/{order}/pickup-slots', [FulfillmentController::class, 'pickupSlots'])->name('pickup-slots');
        Route::post('/{order}/cancel', [FulfillmentController::class, 'cancel'])->name('cancel');
        Route::get('/{order}/label', [FulfillmentController::class, 'label'])->name('label');
        // Task T3: authenticated, in-app streaming route for a courier
        // (SPX) whose own label link is perishable — printLabel() downloads
        // + caches the PDF once, then hands this URL back instead of the
        // provider's own (30-minute, 10-open) link. Same auth as every
        // other route in this group; NOT a public/signed URL.
        Route::get('/{order}/label-file', [FulfillmentController::class, 'labelFile'])->name('label-file');
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
        // Hub (Task W3 split): module-level settings + one card per
        // registered courier. Name frozen at 'admin.fulfillment.settings'.
        Route::get('/', [CourierSettingsController::class, 'index'])->name('settings');
        Route::post('/', [CourierSettingsController::class, 'update'])->name('settings.update');
        // Per-courier page: that courier's own schema fields, actions, and
        // status mapping. Unknown {courier} keys 404 in the controller
        // (CourierManager::get() miss), so this wildcard can never render
        // a page for a courier that isn't registered.
        Route::get('/{courier}', [CourierSettingsController::class, 'show'])->name('settings.courier');
        Route::post('/{courier}', [CourierSettingsController::class, 'updateCourier'])->name('settings.courier.update');
        Route::post('/{courier}/test-connection', [CourierSettingsController::class, 'testConnection'])->name('test-connection');
        Route::post('/{courier}/refresh-statuses', [CourierSettingsController::class, 'refreshStatuses'])->name('settings.refresh-statuses');
        // Task T4: SPX-specific action (3.1 Create Account) — courier-
        // agnostic dispatch (method_exists check in the controller, see its
        // docblock), so unknown/non-supporting couriers get a clear error
        // rather than a 404 or a hardcoded 'spx' literal in the route file.
        Route::post('/{courier}/create-account', [CourierSettingsController::class, 'createShipperAccount'])->name('settings.create-account');
    });

// Public webhook receiver — per-store token guarded, CSRF-exempt (external POST).
Route::post('/webhooks/courier/{provider}', [CourierWebhookController::class, 'handle'])
    ->middleware('web')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('courier.webhook');
