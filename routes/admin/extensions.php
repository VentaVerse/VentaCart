<?php

use App\Http\Controllers\Admin\ExtensionsController;
use App\Http\Controllers\Admin\Marketing\Seo\SeoExtensionController;
use App\Http\Controllers\Admin\Sales\AbandonedCartController;
use Illuminate\Support\Facades\Route;

/*
 * The Extensions area (extension architecture wave 0): the directory of
 * every packaged feature, the lifecycle endpoints, and the settings of
 * extensions mounted under the extensions/{name} URL convention. A
 * top-level area beside Settings, never inside it: Settings is the
 * website's core settings; this door is where optional capability lives.
 */
Route::prefix('extensions')->group(function () {
    // The directory + lifecycle (install/enable/disable; uninstall stays route-only)
    Route::get('/', [ExtensionsController::class, 'index'])->name('extensions.index');
    Route::post('/features/{feature}/enable', [ExtensionsController::class, 'featureEnable'])->name('extensions.feature-enable');
    Route::post('/features/{feature}/disable', [ExtensionsController::class, 'featureDisable'])->name('extensions.feature-disable');
    Route::post('/{name}/install', [ExtensionsController::class, 'install'])->name('extensions.install');
    Route::post('/{name}/enable', [ExtensionsController::class, 'enable'])->name('extensions.enable');
    Route::post('/{name}/disable', [ExtensionsController::class, 'disable'])->name('extensions.disable');
    Route::post('/{name}/uninstall', [ExtensionsController::class, 'uninstall'])->name('extensions.uninstall');

    // Abandoned Carts — configuration (rules, templates, settings). This is
    // a core feature (not an uninstallable extension), so it adopts the
    // extensions/{name} URL *convention* only, without a manifest.
    // Operational pages live at sales/abandoned-carts — see sales.php.
    Route::prefix('abandoned-carts')->group(function () {
        Route::get('/rules', [AbandonedCartController::class, 'rules'])->name('abandoned-carts.rules');
        Route::get('/rules/create', [AbandonedCartController::class, 'createRule'])->name('abandoned-carts.rules.create');
        Route::post('/rules', [AbandonedCartController::class, 'storeRule'])->name('abandoned-carts.rules.store');
        Route::get('/rules/{rule}/edit', [AbandonedCartController::class, 'editRule'])->name('abandoned-carts.rules.edit');
        Route::put('/rules/{rule}', [AbandonedCartController::class, 'updateRule'])->name('abandoned-carts.rules.update');
        Route::delete('/rules/{rule}', [AbandonedCartController::class, 'destroyRule'])->name('abandoned-carts.rules.destroy');

        Route::get('/templates', [AbandonedCartController::class, 'templates'])->name('abandoned-carts.templates');
        Route::get('/templates/create', [AbandonedCartController::class, 'createTemplate'])->name('abandoned-carts.templates.create');
        Route::post('/templates', [AbandonedCartController::class, 'storeTemplate'])->name('abandoned-carts.templates.store');
        Route::get('/templates/{emailTemplate}/edit', [AbandonedCartController::class, 'editTemplate'])->name('abandoned-carts.templates.edit');
        Route::put('/templates/{emailTemplate}', [AbandonedCartController::class, 'updateTemplate'])->name('abandoned-carts.templates.update');
        Route::post('/templates/{emailTemplate}/toggle', [AbandonedCartController::class, 'toggleTemplate'])->name('abandoned-carts.templates.toggle');
        Route::post('/templates/{emailTemplate}/test', [AbandonedCartController::class, 'testTemplate'])->name('abandoned-carts.templates.test');
        Route::delete('/templates/{emailTemplate}', [AbandonedCartController::class, 'destroyTemplate'])->name('abandoned-carts.templates.destroy');

        Route::get('/settings', [AbandonedCartController::class, 'settings'])->name('abandoned-carts.settings');
        Route::post('/settings', [AbandonedCartController::class, 'updateSettings'])->name('abandoned-carts.settings.update');
    });

    // SEO engine config (Claude API key, generation rules, GSC credentials).
    // Same mount-convention-only treatment as abandoned-carts above. SEO
    // *workspace* (daily work) lives at marketing/seo — see marketing.php.
    // Route names keep the 'extensions.seo.' prefix (frozen).
    Route::prefix('seo')->name('extensions.seo.')->group(function () {
        Route::get('/settings', [SeoExtensionController::class, 'settings'])->name('settings');
        Route::post('/settings', [SeoExtensionController::class, 'saveSettings'])->name('save-settings');
        Route::post('/settings/upload-gsc', [SeoExtensionController::class, 'uploadGsc'])->name('upload-gsc');
        Route::post('/settings/test-claude', [SeoExtensionController::class, 'testClaude'])->name('test-claude');
        Route::post('/settings/test-gsc', [SeoExtensionController::class, 'testGsc'])->name('test-gsc');
    });
});
