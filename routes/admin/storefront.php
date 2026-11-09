<?php

use App\Http\Controllers\Admin\Layout\LayoutController;
use App\Http\Controllers\Admin\Layout\MenuController;
use App\Http\Controllers\Admin\Storefront\FooterColumnController;
use App\Http\Controllers\Admin\Storefront\HeaderFooterController;
use Illuminate\Support\Facades\Route;

// Storefront: how the store looks and what sits where. Themes (the theme
// extension's directory), Layout (where things go on each page), Header &
// footer (what sits on every page) and Banners (the banners extension).

// The Layout editor: what renders where, on which pages. Themes without an
// editor of their own ride it; a theme that brings its own editor is
// decoupled and never edited here.
Route::prefix('storefront/layout')->middleware(\App\Http\Middleware\EditTheme::class)->group(function () {
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

// Header & footer: the announcement bar, the top bar links, the main menu
// and the footer columns. One menu, the one the header draws, shared by
// every theme (nav_menus has no theme column).
Route::prefix('storefront/header-footer')->group(function () {
    Route::get('/', [HeaderFooterController::class, 'show'])->name('header-footer');
    Route::post('/announcement', [HeaderFooterController::class, 'updateAnnouncement'])->name('header-footer.announcement');
    Route::post('/top-bar', [HeaderFooterController::class, 'updateTopBar'])->name('header-footer.top-bar');

    Route::get('/menu', [MenuController::class, 'index'])->name('menus');
    Route::post('/menu/{menu}/arrange', [MenuController::class, 'arrange'])->name('menus.arrange');
    Route::post('/menu/{menu}/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
    Route::get('/menu/items/{item}/edit', [MenuController::class, 'editItem'])->name('menus.items.edit');
    Route::put('/menu/items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
    Route::delete('/menu/items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');

    Route::post('/footer', [FooterColumnController::class, 'store'])->name('footer.store');
    Route::post('/footer/arrange', [FooterColumnController::class, 'arrange'])->name('footer.arrange');
    Route::get('/footer/{column}/edit', [FooterColumnController::class, 'edit'])->name('footer.edit');
    Route::put('/footer/{column}', [FooterColumnController::class, 'update'])->name('footer.update');
    Route::delete('/footer/{column}', [FooterColumnController::class, 'destroy'])->name('footer.destroy');
});
