<?php

use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\PageController;
use Illuminate\Support\Facades\Route;

Route::prefix('content')->group(function () {
    Route::resource('pages', PageController::class)->except(['show']);
    Route::resource('blog', BlogPostController::class)->except(['show']);
    Route::resource('blog-categories', BlogCategoryController::class)->except(['show']);
});
