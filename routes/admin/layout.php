<?php

use App\Http\Controllers\Admin\Layout\LayoutController;
use App\Http\Controllers\Admin\Layout\MenuController;
use Illuminate\Support\Facades\Route;

// The Layout editor: what renders where, on which pages. Core since the
// Plaza plan (2026-08-29); it edits store data the active theme reads.
//
// The URL follows the admin's grammar ({admin}/{section}/{entity}) and the
// nav entry, which the owner kept under Extensions > Themes for now. When
// the entry moves, this prefix moves with it; the route names do not.
Route::prefix('extensions/theme/layout')->middleware(\App\Http\Middleware\EditTheme::class)->group(function () {
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

    // The menu composer: menus as trees, core like the layout.
    Route::get('/menus', [MenuController::class, 'index'])->name('layout.menus');
    Route::post('/menus', [MenuController::class, 'storeMenu'])->name('layout.menus.store');
    Route::delete('/menus/{menu}', [MenuController::class, 'destroyMenu'])->name('layout.menus.destroy');
    Route::post('/menus/{menu}/arrange', [MenuController::class, 'arrange'])->name('layout.menus.arrange');
    Route::post('/menus/{menu}/items', [MenuController::class, 'storeItem'])->name('layout.menus.items.store');
    Route::get('/menus/items/{item}/edit', [MenuController::class, 'editItem'])->name('layout.menus.items.edit');
    Route::put('/menus/items/{item}', [MenuController::class, 'updateItem'])->name('layout.menus.items.update');
    Route::delete('/menus/items/{item}', [MenuController::class, 'destroyItem'])->name('layout.menus.items.destroy');
});
