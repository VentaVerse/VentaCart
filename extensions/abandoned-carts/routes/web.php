<?php

use Extensions\AbandonedCarts\Http\Controllers\AbandonedCartController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home (owner ruling 2026-08-19: with the click-memory nav, ops and
// config live together under the extension; the Sales nav entry is a
// pointer in). Route names frozen.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/abandoned-carts')
    ->name('admin.abandoned-carts.')
    ->group(function () {
        Route::get('/', [AbandonedCartController::class, 'index'])->name('index');
        Route::get('/notifications', [AbandonedCartController::class, 'notifications'])->name('notifications');
        Route::get('/recovered', [AbandonedCartController::class, 'recovered'])->name('recovered');
        Route::post('/mass-delete', [AbandonedCartController::class, 'massDelete'])->name('mass-delete');
        // Wildcards last: a single-segment {cart} would swallow the
        // literals above.
        Route::get('/{cart}', [AbandonedCartController::class, 'show'])->name('show')->whereNumber('cart');
        Route::delete('/{cart}', [AbandonedCartController::class, 'destroy'])->name('destroy')->whereNumber('cart');
    });

// Configuration half, same home. Registered FIRST: its literals
// (settings, rules, templates) must beat the ops half's {cart} wildcard.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/abandoned-carts')
    ->name('admin.abandoned-carts.')
    ->group(function () {
        Route::get('/settings', [AbandonedCartController::class, 'settings'])->name('settings');
        Route::post('/settings', [AbandonedCartController::class, 'updateSettings'])->name('settings.update');

        Route::get('/rules', [AbandonedCartController::class, 'rules'])->name('rules');
        Route::get('/rules/create', [AbandonedCartController::class, 'createRule'])->name('rules.create');
        Route::post('/rules', [AbandonedCartController::class, 'storeRule'])->name('rules.store');
        Route::get('/rules/{rule}/edit', [AbandonedCartController::class, 'editRule'])->name('rules.edit');
        Route::put('/rules/{rule}', [AbandonedCartController::class, 'updateRule'])->name('rules.update');
        Route::delete('/rules/{rule}', [AbandonedCartController::class, 'destroyRule'])->name('rules.destroy');

        Route::get('/templates', [AbandonedCartController::class, 'templates'])->name('templates');
        Route::get('/templates/create', [AbandonedCartController::class, 'createTemplate'])->name('templates.create');
        Route::post('/templates', [AbandonedCartController::class, 'storeTemplate'])->name('templates.store');
        Route::get('/templates/{emailTemplate}/edit', [AbandonedCartController::class, 'editTemplate'])->name('templates.edit');
        Route::put('/templates/{emailTemplate}', [AbandonedCartController::class, 'updateTemplate'])->name('templates.update');
        Route::post('/templates/{emailTemplate}/toggle', [AbandonedCartController::class, 'toggleTemplate'])->name('templates.toggle');
        Route::post('/templates/{emailTemplate}/test', [AbandonedCartController::class, 'testTemplate'])->name('templates.test');
        Route::delete('/templates/{emailTemplate}', [AbandonedCartController::class, 'destroyTemplate'])->name('templates.destroy');
    });
