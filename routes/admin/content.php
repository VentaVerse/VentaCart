<?php

use App\Http\Controllers\Admin\Content\MenuController;
use App\Http\Controllers\Admin\Content\PageController;
use Illuminate\Support\Facades\Route;

Route::prefix('content')->group(function () {
    Route::resource('pages', PageController::class)->except(['show']);
    // Blog moved to the blog extension (extensions/blog/routes/web.php).

    // The store's menus, which every theme draws. The route names are
    // read by themes (behind Route::has), so they stay as they are.
    Route::get('/menus', [MenuController::class, 'index'])->name('menus');
    Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
    Route::put('/menus/{menu}', [MenuController::class, 'update'])->whereNumber('menu')->name('menus.update');
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->whereNumber('menu')->name('menus.destroy');
    Route::post('/menus/{menu}/arrange', [MenuController::class, 'arrange'])->name('menus.arrange');
    Route::post('/menus/{menu}/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
    Route::get('/menus/items/{item}/edit', [MenuController::class, 'editItem'])->name('menus.items.edit');
    Route::put('/menus/items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
    Route::delete('/menus/items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');
});
