<?php

use Extensions\EmailCampaigns\Http\Controllers\AdminEmailCampaignController;
use Extensions\EmailCampaigns\Http\Controllers\AdminEmailCampaignTemplateController;
use Extensions\EmailCampaigns\Http\Controllers\NewsletterSubscriptionController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home: everything this extension owns lives under
// extensions/email-campaigns; the Marketing nav entry is a pointer in.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/email-campaigns')
    ->name('admin.email-campaigns.')
    ->group(function () {
        // Literals before the {campaign} wildcard so it cannot swallow them.
        Route::get('/recipient-count', [AdminEmailCampaignController::class, 'recipientCount'])->name('recipient-count');
        Route::post('/test', [AdminEmailCampaignController::class, 'test'])->name('test');

        // Templates before the {campaign} wildcard so it cannot swallow them.
        Route::get('/templates', [AdminEmailCampaignTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [AdminEmailCampaignTemplateController::class, 'create'])->name('templates.create');
        Route::post('/templates', [AdminEmailCampaignTemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}/edit', [AdminEmailCampaignTemplateController::class, 'edit'])->name('templates.edit')->whereNumber('template');
        Route::put('/templates/{template}', [AdminEmailCampaignTemplateController::class, 'update'])->name('templates.update')->whereNumber('template');
        Route::delete('/templates/{template}', [AdminEmailCampaignTemplateController::class, 'destroy'])->name('templates.destroy')->whereNumber('template');

        Route::get('/', [AdminEmailCampaignController::class, 'index'])->name('index');
        Route::get('/create', [AdminEmailCampaignController::class, 'create'])->name('create');
        Route::post('/', [AdminEmailCampaignController::class, 'store'])->name('store');
        Route::get('/{campaign}/edit', [AdminEmailCampaignController::class, 'edit'])->name('edit')->whereNumber('campaign');
        Route::put('/{campaign}', [AdminEmailCampaignController::class, 'update'])->name('update')->whereNumber('campaign');
        Route::delete('/{campaign}', [AdminEmailCampaignController::class, 'destroy'])->name('destroy')->whereNumber('campaign');
        Route::post('/{campaign}/duplicate', [AdminEmailCampaignController::class, 'duplicate'])->name('duplicate')->whereNumber('campaign');
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
