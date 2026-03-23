<?php

use Extensions\Banners\Http\Controllers\BannerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin/banners')->name('admin.banners.')->group(function () {
    Route::get('/', [BannerController::class, 'index'])->name('index');
    Route::get('/create', [BannerController::class, 'create'])->name('create');
    Route::post('/', [BannerController::class, 'store'])->name('store');
    Route::get('/{banner}/edit', [BannerController::class, 'edit'])->name('edit');
    Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
    Route::delete('/{banner}', [BannerController::class, 'destroy'])->name('destroy');
});
