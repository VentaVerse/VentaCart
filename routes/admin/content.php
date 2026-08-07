<?php

use App\Http\Controllers\Admin\Content\BlogCategoryController;
use App\Http\Controllers\Admin\Content\BlogPostController;
use App\Http\Controllers\Admin\Content\PageController;
use Illuminate\Support\Facades\Route;

Route::prefix('content')->group(function () {
    Route::resource('pages', PageController::class)->except(['show']);
    Route::resource('blog', BlogPostController::class)->except(['show']);
    Route::resource('blog-categories', BlogCategoryController::class)->except(['show']);
});
