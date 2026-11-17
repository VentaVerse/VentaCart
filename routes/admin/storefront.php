<?php

use App\Http\Controllers\Admin\Storefront\FooterColumnController;
use App\Http\Controllers\Admin\Storefront\HeaderFooterController;
use Illuminate\Support\Facades\Route;

// Storefront: what sits on every page, whatever the theme. Themes are
// chosen in the theme extension's directory.

// Header & footer: the announcement bar, the top bar links and the footer
// columns. The menu is edited at Content > Menus.
Route::prefix('storefront/header-footer')->group(function () {
    Route::get('/', [HeaderFooterController::class, 'show'])->name('header-footer');
    Route::post('/announcement', [HeaderFooterController::class, 'updateAnnouncement'])->name('header-footer.announcement');
    Route::post('/top-bar', [HeaderFooterController::class, 'updateTopBar'])->name('header-footer.top-bar');

    Route::post('/footer', [FooterColumnController::class, 'store'])->name('footer.store');
    Route::post('/footer/arrange', [FooterColumnController::class, 'arrange'])->name('footer.arrange');
    Route::get('/footer/{column}/edit', [FooterColumnController::class, 'edit'])->name('footer.edit');
    Route::put('/footer/{column}', [FooterColumnController::class, 'update'])->name('footer.update');
    Route::delete('/footer/{column}', [FooterColumnController::class, 'destroy'])->name('footer.destroy');
});
