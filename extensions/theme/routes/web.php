<?php

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
        // Under /package so the core extension routes (/extensions/{name}/install)
        // do not take these for the editor extension itself.
        Route::post('/package/install', [ThemeController::class, 'install'])->name('package.install');
        Route::post('/package/uninstall', [ThemeController::class, 'uninstall'])->name('package.uninstall');
    });
