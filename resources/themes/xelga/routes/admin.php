<?php

use Illuminate\Support\Facades\Route;
use Themes\Xelga\Http\Controllers\DocumentController;
use Themes\Xelga\Http\Controllers\EditorController;
use Themes\Xelga\Http\Controllers\PickerController;
use Themes\Xelga\Http\Controllers\PresenceController;
use Themes\Xelga\Http\Controllers\SchemeController;
use Themes\Xelga\Http\Controllers\StoreTemplateController;
use Themes\Xelga\Http\Controllers\TemplateController;

// The theme editor and the JSON endpoints it works through. Every route is
// admin.xelga.*, mapped to the themes.edit permission by the provider, and
// limited per admin (XelgaServiceProvider::LIMITS).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix(trim((string) config('admin.prefix', 'admin'), '/') . '/extensions/theme/xelga')
    ->name('admin.xelga.')
    ->group(function () {
        // Underscores for templates of page types extensions added (`gift_registry`).
        $handle = '[a-z0-9][a-z0-9._\-]{0,63}';

        Route::get('/editor', [EditorController::class, 'show'])->middleware('throttle:xelga-editor')->name('editor');
        Route::post('/preview', [EditorController::class, 'openPreview'])->middleware('throttle:xelga-preview')->name('preview.open');
        Route::delete('/preview', [EditorController::class, 'closePreview'])->middleware('throttle:xelga-preview')->name('preview.close');

        Route::delete('/documents/draft', [DocumentController::class, 'discard'])->middleware('throttle:xelga-publish')->name('documents.discard');
        Route::get('/documents/{handle}', [DocumentController::class, 'show'])->where('handle', $handle)->middleware('throttle:xelga-read')->name('documents.show');
        Route::put('/documents/{handle}/draft', [DocumentController::class, 'saveDraft'])->where('handle', $handle)->middleware('throttle:xelga-draft')->name('documents.draft');
        Route::post('/documents/{handle}/duplicate', [DocumentController::class, 'duplicate'])->where('handle', $handle)->middleware('throttle:xelga-draft')->name('documents.duplicate');
        Route::get('/documents/{handle}/revisions', [DocumentController::class, 'revisions'])->where('handle', $handle)->middleware('throttle:xelga-revisions')->name('revisions.index');
        Route::post('/documents/{handle}/revisions/{revision}/restore', [DocumentController::class, 'restore'])->where('handle', $handle)->whereNumber('revision')->middleware('throttle:xelga-publish')->name('revisions.restore');
        Route::post('/publish', [DocumentController::class, 'publish'])->middleware('throttle:xelga-publish')->name('publish');

        $custom = '[a-z][a-z\-]{0,15}\.[a-z0-9][a-z0-9\-]{0,62}';
        Route::get('/templates', [TemplateController::class, 'index'])->middleware('throttle:xelga-read')->name('templates.index');
        Route::post('/templates', [TemplateController::class, 'store'])->middleware('throttle:xelga-templates')->name('templates.store');
        Route::patch('/templates/{handle}', [TemplateController::class, 'update'])->where('handle', $custom)->middleware('throttle:xelga-templates')->name('templates.update');
        Route::delete('/templates/{handle}', [TemplateController::class, 'destroy'])->where('handle', $custom)->middleware('throttle:xelga-templates')->name('templates.destroy');
        Route::put('/assignments', [TemplateController::class, 'assign'])->middleware('throttle:xelga-assign')->name('assignments.update');

        Route::post('/presence', [PresenceController::class, 'heartbeat'])->middleware('throttle:xelga-presence')->name('presence');
        // Starting stores: apply one to the draft, or take the last apply off again.
        Route::post('/store-templates/undo', [StoreTemplateController::class, 'undo'])->middleware('throttle:xelga-store-templates')->name('store-templates.undo');
        Route::post('/store-templates/{slug}/apply', [StoreTemplateController::class, 'apply'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:xelga-store-templates')->name('store-templates.apply');

        Route::get('/schemes/usage', [SchemeController::class, 'usage'])->middleware('throttle:xelga-schemes')->name('schemes.usage');

        Route::get('/pick/{kind}', [PickerController::class, 'pick'])->where('kind', '[a-z\-]+')->middleware('throttle:xelga-pick')->name('pick');
    });
