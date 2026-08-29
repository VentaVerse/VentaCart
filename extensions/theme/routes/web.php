<?php

use Extensions\Theme\Http\Controllers\FooterColumnController;
use Extensions\Theme\Http\Controllers\NavMenuController;
use Extensions\Theme\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// The theme editor, mounted under the extensions/{name} convention.
// Route names frozen (admin.theme.*); only the mount moved with the
// conversion. admin.theme.currencies.* is a separate, core family
// (Settings > Currencies CRUD) and never lived here.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/theme')
    ->name('admin.theme.')
    ->group(function () {
        // The extension's home is the DIRECTORY, like every other extension
        // page: which themes are installed, which one the storefront runs.
        Route::get('/', [ThemeController::class, 'directory'])->name('index');
        Route::post('/activate', [ThemeController::class, 'activate'])->name('activate');
        // The editor for one theme, reached by choosing it.
        Route::get('/customise', [ThemeController::class, 'index'])->name('customise');
        // The layout editor. Its own work page, not a tab inside the theme
        // editor: placements are store data that survive a theme switch.
        // The bench: storefront in a frame, controls beside it.
        Route::get('/preview', [ThemeController::class, 'preview'])->name('preview');
        Route::post('/preview', [ThemeController::class, 'savePreview'])->name('preview.save');
        Route::post('/settings', [ThemeController::class, 'updateSettings'])->name('settings.update');
        Route::post('/switch', [ThemeController::class, 'switchTheme'])->name('switch');
        Route::post('/revert-colors', [ThemeController::class, 'revertThemeColors'])->name('revert-colors');
        Route::post('/presets', [ThemeController::class, 'storePreset'])->name('presets.store');
        Route::post('/presets/import', [ThemeController::class, 'importPreset'])->name('presets.import');
        Route::get('/presets/{slug}/export', [ThemeController::class, 'exportPreset'])->name('presets.export');
        Route::delete('/presets/{slug}', [ThemeController::class, 'destroyPreset'])->name('presets.destroy');
        // The homepage-section editor is gone (2026-08-21). It edited
        // homepage_sections, which no storefront view has rendered since the
        // layout system landed: a working form with a Save that reported
        // success and changed nothing. The data was carried across by
        // layout:carry-homepage-sections; the model stays for that command.
        Route::post('/theme-settings', [ThemeController::class, 'updateThemeSettings'])->name('update-theme-settings');


        Route::post('/nav', [NavMenuController::class, 'store'])->name('nav.store');
        Route::get('/nav/{item}/edit', [NavMenuController::class, 'edit'])->name('nav.edit');
        Route::put('/nav/{item}', [NavMenuController::class, 'update'])->name('nav.update');
        Route::delete('/nav/{item}', [NavMenuController::class, 'destroy'])->name('nav.destroy');
        Route::post('/nav/reorder', [NavMenuController::class, 'reorder'])->name('nav.reorder');

        Route::post('/footer', [FooterColumnController::class, 'store'])->name('footer.store');
        Route::post('/footer/bulk-update', [FooterColumnController::class, 'bulkUpdate'])->name('footer.bulk-update');
        Route::get('/footer/{column}/edit', [FooterColumnController::class, 'edit'])->name('footer.edit');
        Route::put('/footer/{column}', [FooterColumnController::class, 'update'])->name('footer.update');
        Route::delete('/footer/{column}', [FooterColumnController::class, 'destroy'])->name('footer.destroy');
        Route::post('/footer/reorder', [FooterColumnController::class, 'reorder'])->name('footer.reorder');
    });
