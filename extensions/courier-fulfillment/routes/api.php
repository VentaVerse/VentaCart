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
});
