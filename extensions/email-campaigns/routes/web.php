<?php

use Extensions\EmailCampaigns\Http\Controllers\AdminEmailCampaignController;
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
