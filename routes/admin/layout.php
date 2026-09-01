<?php

use App\Http\Controllers\Admin\Layout\LayoutController;
use App\Http\Controllers\Admin\Layout\MenuController;
use Illuminate\Support\Facades\Route;

// The Layout editor: what renders where, on which pages. OpenCart's shape
// by the owner's ruling (2026-08-31): the layout system stands on its own
// under Content, and themes without an editor of their own ride it. A theme
// that brings its own editor (Plaza) is decoupled and never edited here.
Route::prefix('content/layout')->middleware(\App\Http\Middleware\EditTheme::class)->group(function () {
    Route::get('/', [LayoutController::class, 'index'])->name('layout');
    Route::post('/', [LayoutController::class, 'update'])->name('layout.update');
    Route::get('/schemas', [LayoutController::class, 'schemas'])->name('layout.schemas');
    Route::post('/style-presets', [LayoutController::class, 'storeStylePreset'])->name('layout.style-presets.store');
    Route::delete('/style-presets/{preset}', [LayoutController::class, 'destroyStylePreset'])->name('layout.style-presets.destroy');
    Route::post('/templates', [LayoutController::class, 'storeTemplate'])->name('layout.templates.store');
    Route::delete('/templates/{template}', [LayoutController::class, 'destroyTemplate'])->name('layout.templates.destroy');
    Route::post('/templates/export', [LayoutController::class, 'exportTemplate'])->name('layout.templates.export');
    Route::post('/templates/import', [LayoutController::class, 'importTemplate'])->name('layout.templates.import');
    Route::post('/copy', [LayoutController::class, 'copyRegion'])->name('layout.copy');
});

// The menus. One menu is shared by every theme (nav_menus has no theme
// column), so it is not a theme's to own, and it is edited as often as the
// pages beside it. One door, under Content, and no second one anywhere.
// One menu, the one the header draws. Creating a second was a capability
// with no payoff: the header is not switchable and an extra menu could only
// surface as a placed widget. Menus that do more are Plaza's to bring.
Route::prefix('content/menus')->group(function () {
    Route::get('/', [MenuController::class, 'index'])->name('menus');
    Route::post('/{menu}/arrange', [MenuController::class, 'arrange'])->name('menus.arrange');
    Route::post('/{menu}/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
    Route::get('/items/{item}/edit', [MenuController::class, 'editItem'])->name('menus.items.edit');
    Route::put('/items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
    Route::delete('/items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');
});
