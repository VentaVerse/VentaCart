<?php

use Extensions\EmailCampaigns\Http\Controllers\AdminEmailCampaignController;
use Extensions\EmailCampaigns\Http\Controllers\NewsletterSubscriptionController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home: everything this extension owns lives under
// extensions/email-campaigns; the Marketing nav entry is a pointer in.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/email-campaigns')
    ->name('admin.email-campaigns.')
    ->group(function () {
        Route::get('/', [AdminEmailCampaignController::class, 'index'])->name('index');
    });

// Storefront, signed and public: the link inside a campaign. `signed`
// middleware 403s a tampered or unsigned request. Two routes rather than one
// with a flag, because Laravel signs the full query string and a resubscribe
// parameter appended to an unsubscribe link would break its own signature.
Route::middleware(['web', 'signed', 'noindex'])->group(function () {
    Route::get('/newsletter/unsubscribe/{customer}', [NewsletterSubscriptionController::class, 'unsubscribe'])
        ->name('newsletter.unsubscribe')->whereNumber('customer');

    Route::get('/newsletter/resubscribe/{customer}', [NewsletterSubscriptionController::class, 'resubscribe'])
        ->name('newsletter.resubscribe')->whereNumber('customer');
});
