<?php

use Extensions\Reviews\Http\Controllers\ReviewController;
use Extensions\Reviews\Http\Controllers\StorefrontReviewController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home (owner ruling 2026-08-19: ops and config together under the
// extension; the Sales nav entry is a pointer in). Route names frozen.
// The settings literal beats the {review} wildcard by order.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/reviews')
    ->name('admin.reviews.')
    ->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::get('/settings', [ReviewController::class, 'settings'])->name('settings');
        Route::get('/{review}', [ReviewController::class, 'show'])->name('show')->whereNumber('review');
        Route::post('/{review}/approve', [ReviewController::class, 'approve'])->name('approve');
        Route::post('/{review}/reject', [ReviewController::class, 'reject'])->name('reject');
        Route::delete('/{review}', [ReviewController::class, 'destroy'])->name('destroy');
    });

// Storefront: the tokened submission page the request email links to
// (public, token-guarded — same middleware posture as before the move).
// URLs frozen; disabled extension = 404 and no emails go out anyway.
Route::middleware('web')->group(function () {
    Route::get('/review/{token}', [StorefrontReviewController::class, 'show'])->name('review.submit');
    Route::post('/review/{token}', [StorefrontReviewController::class, 'store'])->name('review.store');
});
