<?php

use App\Http\Controllers\Admin\Catalog\BrandController;
use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\MediaController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\Catalog\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->group(function () {
    // Products
    Route::post('products/quick-create', [ProductController::class, 'quickCreate'])->name('products.quick-create');
    Route::resource('products', ProductController::class)->except(['show']);

    // Product Image Manager
    Route::post('/products/images/upload', [ProductImageController::class, 'upload'])->name('products.images.upload');
    Route::post('/products/images/import-url', [ProductImageController::class, 'importUrl'])->name('products.images.import-url');
    Route::get('/products/images/browse', [ProductImageController::class, 'browse'])->name('products.images.browse');
    Route::post('/products/images/upload-to-catalog', [ProductImageController::class, 'uploadToCatalog'])->name('products.images.upload-to-catalog');
    Route::post('/products/images/delete', [ProductImageController::class, 'delete'])->name('products.images.delete');

    // Categories
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');

    // Brands
    Route::get('/brands/search', [BrandController::class, 'search'])->name('brands.search');
    Route::post('/brands/quick-create', [BrandController::class, 'quickCreate'])->name('brands.quick-create');
    Route::resource('brands', BrandController::class)->except(['show']);

    // Media Manager
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/folders', [MediaController::class, 'folders'])->name('media.folders');
    Route::post('/media/folders', [MediaController::class, 'createFolder'])->name('media.folders.store');
    Route::put('/media/folders/{folder}', [MediaController::class, 'updateFolder'])->name('media.folders.update');
    Route::delete('/media/folders/{folder}', [MediaController::class, 'deleteFolder'])->name('media.folders.destroy');
    Route::get('/media/list', [MediaController::class, 'list'])->name('media.list');
    Route::post('/media/upload', [MediaController::class, 'store'])->name('media.upload');
    Route::delete('/media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('/media/cleanup-temp', [MediaController::class, 'cleanupTemp'])->name('media.cleanup-temp');

    // Tags
    Route::resource('tags', TagController::class)->except(['show']);
});
