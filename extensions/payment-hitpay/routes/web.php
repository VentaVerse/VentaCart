<?php

use Extensions\PaymentHitpay\Http\Controllers\HitPayWebhookController;
use Illuminate\Support\Facades\Route;

// HitPay webhook (no CSRF, no auth — signature-verified). Storefront
// URL frozen; disabled extension = 404, which is correct: no payments
// were initiated through it.
Route::middleware('web')->group(function () {
    Route::post('/hitpay/webhook', HitPayWebhookController::class)
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('hitpay.webhook');
});
