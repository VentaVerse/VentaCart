<?php

use App\Http\Controllers\Admin\Layout\LayoutController;
use Illuminate\Support\Facades\Route;

// The Layout editor: what renders where, on which pages. Core since the
// Plaza plan (2026-08-29); it edits store data the active theme reads.
//
// The URL follows the admin's grammar ({admin}/{section}/{entity}) and the
// nav entry, which the owner kept under Extensions > Themes for now. When
// the entry moves, this prefix moves with it; the route names do not.
Route::prefix('extensions/theme/layout')->group(function () {
    Route::get('/', [LayoutController::class, 'index'])->name('layout');
    Route::post('/', [LayoutController::class, 'update'])->name('layout.update');
    Route::get('/schemas', [LayoutController::class, 'schemas'])->name('layout.schemas');
});
