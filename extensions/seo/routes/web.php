<?php

use Extensions\Seo\Http\Controllers\SeoExtensionController;
use Extensions\Seo\Http\Controllers\SeoNotFoundController;
use Extensions\Seo\Http\Controllers\SeoRedirectController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home (owner ruling 2026-08-19): the whole space, workspace and
// engine settings alike, under the extension; the Marketing nav entry
// is a pointer in. Route names frozen ('admin.extensions.seo.').
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/seo')
    ->name('admin.extensions.seo.')
    ->group(function () {
        Route::get('/', [SeoExtensionController::class, 'dashboard'])->name('dashboard');
        Route::get('/search-console', [SeoExtensionController::class, 'searchConsole'])->name('search-console');
        Route::get('/strategy', [SeoExtensionController::class, 'strategy'])->name('strategy');
        Route::get('/review', [SeoExtensionController::class, 'review'])->name('review');
        Route::get('/slugs', [SeoExtensionController::class, 'slugs'])->name('slugs');
        Route::post('/slugs/update', [SeoExtensionController::class, 'updateSlug'])->name('update-slug');
        Route::post('/strategy', [SeoExtensionController::class, 'saveStrategy'])->name('save-strategy');
        Route::post('/generate', [SeoExtensionController::class, 'generate'])->name('generate');
        Route::post('/optimize', [SeoExtensionController::class, 'optimize'])->name('optimize');
        Route::post('/suggestions/{suggestion}/accept', [SeoExtensionController::class, 'accept'])->name('accept');
        Route::post('/suggestions/{suggestion}/reject', [SeoExtensionController::class, 'reject'])->name('reject');
        Route::post('/suggestions/bulk-accept', [SeoExtensionController::class, 'bulkAccept'])->name('bulk-accept');

        Route::get('/redirects', [SeoRedirectController::class, 'index'])->name('redirects.index');
        Route::post('/redirects', [SeoRedirectController::class, 'store'])->name('redirects.store');
        Route::put('/redirects/{redirect}', [SeoRedirectController::class, 'update'])->name('redirects.update');
        Route::delete('/redirects/{redirect}', [SeoRedirectController::class, 'destroy'])->name('redirects.destroy');

        Route::get('/not-found', [SeoNotFoundController::class, 'index'])->name('not-found.index');
        Route::delete('/not-found/{log}', [SeoNotFoundController::class, 'destroy'])->name('not-found.destroy');
        Route::post('/not-found/clear', [SeoNotFoundController::class, 'clear'])->name('not-found.clear');

        // Wildcard LAST: it would swallow the literals above.
        Route::get('/{type}', [SeoExtensionController::class, 'entities'])->name('entities')->where('type', 'products|categories|brands|blog|pages');
    });

// Engine configuration, same home. Route names frozen.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/seo')
    ->name('admin.extensions.seo.')
    ->group(function () {
        Route::get('/settings', [SeoExtensionController::class, 'settings'])->name('settings');
        Route::post('/settings', [SeoExtensionController::class, 'saveSettings'])->name('save-settings');
        Route::post('/settings/upload-gsc', [SeoExtensionController::class, 'uploadGsc'])->name('upload-gsc');
        Route::post('/settings/test-claude', [SeoExtensionController::class, 'testClaude'])->name('test-claude');
        Route::post('/settings/test-gsc', [SeoExtensionController::class, 'testGsc'])->name('test-gsc');
    });
