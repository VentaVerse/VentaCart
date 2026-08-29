<?php

use App\Http\Controllers\Admin\Layout\LayoutController;
use Illuminate\Support\Facades\Route;

// The Layout editor: what renders where, on which pages. Core since the
// Plaza plan (2026-08-29); it edits store data the active theme reads.
Route::get('/layout', [LayoutController::class, 'index'])->name('layout');
Route::post('/layout', [LayoutController::class, 'update'])->name('layout.update');
Route::get('/layout/schemas', [LayoutController::class, 'schemas'])->name('layout.schemas');
