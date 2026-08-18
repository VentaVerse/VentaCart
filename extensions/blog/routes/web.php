<?php

use Extensions\Blog\Http\Controllers\BlogCategoryController;
use Extensions\Blog\Http\Controllers\BlogPostController;
use Extensions\Blog\Http\Controllers\StorefrontBlogController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Admin, in the Content work nav. Route names frozen
// (admin.blog.*, admin.blog-categories.*).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/content')
    ->name('admin.')
    ->group(function () {
        Route::resource('blog', BlogPostController::class)->except(['show']);
        Route::resource('blog-categories', BlogCategoryController::class)->except(['show']);
    });

// Storefront blog. URLs frozen; disabled extension = 404, and the theme
// hides its nav links through the nav_menu_items the merchant controls.
Route::middleware('web')->group(function () {
    Route::get('/blog', [StorefrontBlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{post:slug}', [StorefrontBlogController::class, 'show'])->name('blog.show');
});
