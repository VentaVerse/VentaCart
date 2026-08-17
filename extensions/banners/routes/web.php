<?php

use Extensions\Banners\Http\Controllers\BannerController;
use Illuminate\Support\Facades\Route;

// Mounted under the *configured* admin prefix at settings/modules/banners
// (the kernel-mount convention) — never a hardcoded 'admin/' literal. The
// old hardcoded 'admin/banners/*' paths now 301 via routes/admin.php's
// legacy-banners-redirect route. Route names are unchanged ('admin.banners.*').
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix(config('admin.prefix', 'admin').'/extensions/banners')
    ->name('admin.banners.')
    ->group(function () {
    // Groups
    Route::get('/', [BannerController::class, 'index'])->name('index');
    Route::get('/create', [BannerController::class, 'createGroup'])->name('create');
    Route::post('/', [BannerController::class, 'storeGroup'])->name('store');
    Route::get('/{group}/edit', [BannerController::class, 'editGroup'])->name('edit');
    Route::put('/{group}', [BannerController::class, 'updateGroup'])->name('update');
    Route::delete('/{group}', [BannerController::class, 'destroyGroup'])->name('destroy');

    // Slides within a group
    Route::get('/{group}/slides', [BannerController::class, 'slides'])->name('slides');
    Route::get('/{group}/slides/create', [BannerController::class, 'create'])->name('slides.create');
    Route::post('/{group}/slides', [BannerController::class, 'store'])->name('slides.store');
    Route::get('/{group}/slides/{banner}/edit', [BannerController::class, 'edit'])->name('slides.edit');
    Route::put('/{group}/slides/{banner}', [BannerController::class, 'update'])->name('slides.update');
    Route::post('/{group}/slides/{banner}/duplicate', [BannerController::class, 'duplicate'])->name('slides.duplicate');
    Route::delete('/{group}/slides/{banner}', [BannerController::class, 'destroy'])->name('slides.destroy');
});
