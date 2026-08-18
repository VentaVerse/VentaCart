<?php

use Extensions\Reviews\Http\Controllers\ReviewController;
use Extensions\Reviews\Http\Controllers\StorefrontReviewController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Moderation, in the Sales work nav. Route names frozen (admin.reviews.*).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/sales/reviews')
    ->name('admin.reviews.')
    ->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::get('/{review}', [ReviewController::class, 'show'])->name('show');
        Route::post('/{review}/approve', [ReviewController::class, 'approve'])->name('approve');
        Route::post('/{review}/reject', [ReviewController::class, 'reject'])->name('reject');
        Route::delete('/{review}', [ReviewController::class, 'destroy'])->name('destroy');
    });

// Configuration, behind the Extensions door (owner ruling: ops and
// config separate, config never in Settings). The form itself posts to
// the core settings endpoint (group reviews) as before.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/reviews')
    ->name('admin.reviews.')
    ->group(function () {
        Route::get('/settings', [ReviewController::class, 'settings'])->name('settings');
    });

// Storefront: the tokened submission page the request email links to
// (public, token-guarded — same middleware posture as before the move).
// URLs frozen; disabled extension = 404 and no emails go out anyway.
Route::middleware('web')->group(function () {
    Route::get('/review/{token}', [StorefrontReviewController::class, 'show'])->name('review.submit');
    Route::post('/review/{token}', [StorefrontReviewController::class, 'store'])->name('review.store');
});
