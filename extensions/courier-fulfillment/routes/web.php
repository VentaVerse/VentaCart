<?php

use Extensions\CourierFulfillment\Http\Controllers\CourierSettingsController;
use Extensions\CourierFulfillment\Http\Controllers\CourierWebhookController;
use Extensions\CourierFulfillment\Http\Controllers\FulfillmentController;
use Extensions\CourierFulfillment\Http\Controllers\PickupAddressController;
use Extensions\CourierFulfillment\Http\Controllers\StatusPlacementController;
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
        // The third way a parcel can leave: a courier we do not integrate
        // with, recorded by hand (no API call, no AWB, no polling). Writes
        // core's own shipping_courier_id / tracking_number / tracking_link and
        // leaves courier_provider null, which is what keeps courier:poll-status
        // from ever seeing it. clear-manual is its undo.
        Route::post('/{order}/book-manual', [FulfillmentController::class, 'bookManual'])->name('book-manual');
        Route::post('/{order}/clear-manual', [FulfillmentController::class, 'clearManual'])->name('clear-manual');
        // Task T4: lazy, per-order/per-selection AJAX endpoints the booking
        // modal's step 2 hits on demand (SPX's estimate button / pickup-slot
        // fetch on courier choice) — never called during the queue's own
        // page load. See FulfillmentController::estimate()/pickupSlots()'s
        // docblocks for why these are lazy rather than baked into
        // index()'s courierOptions payload.
        Route::post('/{order}/estimate', [FulfillmentController::class, 'estimate'])->name('estimate');
        Route::get('/{order}/pickup-slots', [FulfillmentController::class, 'pickupSlots'])->name('pickup-slots');
        // Owner directive (2026-08-12, "the booking modal says who will
        // carry it before you choose"): every configured courier's answer to
        // "do you serve this address", asked when the booking modal opens.
        // Same laziness rule as the two endpoints above: never on page
        // load. GET because it asks and creates nothing.
        Route::get('/{order}/serviceability', [FulfillmentController::class, 'serviceability'])->name('serviceability');
        Route::post('/{order}/cancel', [FulfillmentController::class, 'cancel'])->name('cancel');
        // fulfillment-tabs-v3 Task 4: the REAL order cancel (sets the core
        // order status to the module's configured cancel status through the
        // same history + stock path the order page uses) — distinct from
        // /cancel above, which cancels only the courier BOOKING.
        Route::post('/{order}/cancel-order', [FulfillmentController::class, 'cancelOrder'])->name('cancel-order');
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
        // Status placement: which queue tab each core order status belongs to
        // (owner ruling, 2026-08-09 - the mapping is module data, so it is
        // edited here rather than on core's Order Statuses page). STATIC
        // segment, declared BEFORE the '/{courier}' wildcard below so it can
        // never be captured as a courier key.
        Route::get('/status-placement', [StatusPlacementController::class, 'index'])->name('settings.placement');
        Route::post('/status-placement', [StatusPlacementController::class, 'update'])->name('settings.placement.update');
        // Pickup addresses: the places parcels leave from, owned by this
        // module (courier_fulfillment_pickup_addresses). STATIC segment,
        // declared BEFORE the '/{courier}' wildcard below for the same
        // reason status-placement is - otherwise 'pickup-addresses' would be
        // captured as a courier key and 404.
        Route::get('/pickup-addresses', [PickupAddressController::class, 'index'])->name('pickup-addresses.index');
        Route::get('/pickup-addresses/create', [PickupAddressController::class, 'create'])->name('pickup-addresses.create');
        Route::post('/pickup-addresses', [PickupAddressController::class, 'store'])->name('pickup-addresses.store');
        Route::get('/pickup-addresses/{pickupAddress}/edit', [PickupAddressController::class, 'edit'])->name('pickup-addresses.edit');
        Route::put('/pickup-addresses/{pickupAddress}', [PickupAddressController::class, 'update'])->name('pickup-addresses.update');
        Route::post('/pickup-addresses/{pickupAddress}/default', [PickupAddressController::class, 'makeDefault'])->name('pickup-addresses.default');
        Route::delete('/pickup-addresses/{pickupAddress}', [PickupAddressController::class, 'destroy'])->name('pickup-addresses.destroy');
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
