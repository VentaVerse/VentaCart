<?php

use App\Http\Controllers\Admin\Catalog\BrandController;
use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\MediaController;
use App\Http\Controllers\Admin\Catalog\MediaLibraryController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\Catalog\ProductImageController;
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
    // One library, one namespace. The eight routes that used to sit here were
    // reachable, authenticated, state-changing, and called by nothing: the
    // Media page itself ran on the product image endpoints, and StoreMedia
    // never held a row. They went with the controller.
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/browse', [MediaLibraryController::class, 'browse'])->name('media.browse');
    Route::post('/media/upload', [MediaLibraryController::class, 'upload'])->name('media.upload');
    Route::post('/media/import-url', [MediaLibraryController::class, 'importUrl'])->name('media.import-url');
    Route::post('/media/delete', [MediaLibraryController::class, 'destroy'])->name('media.delete');
    Route::post('/media/folder', [MediaLibraryController::class, 'folder'])->name('media.folder');

    // Tags
    Route::resource('tags', TagController::class)->except(['show']);
});
