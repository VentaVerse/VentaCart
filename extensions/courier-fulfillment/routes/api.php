<?php

use Extensions\CourierFulfillment\Http\Controllers\ErpFulfillmentController;
use Illuminate\Support\Facades\Route;

/**
 * ERP -> Venta -> courier. The ERP (order hub) triggers booking for a Venta
 * order; Venta owns the courier integration and executes against QuadX.
 *
 * Loaded via the extension's service provider (not the app's api routing), so we
 * spell out the full `api/v1` prefix and the shared `erp.auth` guard (Bearer
 * token vs the encrypted api.erp_token setting) ourselves. Stateless: no web
 * session / CSRF, since it's loaded outside the `web` group.
 */
Route::prefix('api/v1')->middleware('erp.auth')->group(function () {
    Route::post('/orders/{id}/book-courier', [ErpFulfillmentController::class, 'book'])
        ->whereNumber('id')
        ->name('erp.orders.book-courier');

    // The rest of the fulfillment surface, so the ERP can run the whole
    // shipment lifecycle without an admin in the loop. Each endpoint
    // reuses the exact code path the admin queue runs (shared
    // FulfillmentService / ServiceabilityService / courier adapters).
    Route::post('/orders/{id}/cancel-booking', [ErpFulfillmentController::class, 'cancelBooking'])
        ->whereNumber('id')
        ->name('erp.orders.cancel-booking');
    Route::get('/orders/{id}/tracking', [ErpFulfillmentController::class, 'tracking'])
        ->whereNumber('id')
        ->name('erp.orders.tracking');
    Route::get('/orders/{id}/label', [ErpFulfillmentController::class, 'label'])
        ->whereNumber('id')
        ->name('erp.orders.label');
    Route::get('/orders/{id}/serviceability', [ErpFulfillmentController::class, 'serviceability'])
        ->whereNumber('id')
        ->name('erp.orders.serviceability');
    Route::post('/orders/{id}/estimate', [ErpFulfillmentController::class, 'estimate'])
        ->whereNumber('id')
        ->name('erp.orders.estimate');
    Route::get('/orders/{id}/pickup-slots', [ErpFulfillmentController::class, 'pickupSlots'])
        ->whereNumber('id')
        ->name('erp.orders.pickup-slots');
});
