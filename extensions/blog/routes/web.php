<?php

use Extensions\Blog\Http\Controllers\BlogCategoryController;
use Extensions\Blog\Http\Controllers\BlogPostController;
use Extensions\Blog\Http\Controllers\StorefrontBlogController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home (owner ruling 2026-08-19); the Content nav entries are
// pointers in. Route names frozen (admin.blog.*, admin.blog-categories.*).
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/blog')
    ->name('admin.')
    ->group(function () {
        Route::resource('posts', BlogPostController::class)->except(['show'])->parameters(['posts' => 'blog'])->names('blog');
        Route::resource('categories', BlogCategoryController::class)->except(['show'])->parameters(['categories' => 'blog_category'])->names('blog-categories');
    });

// Storefront blog. URLs frozen; disabled extension = 404, and the theme
// hides its nav links through the nav_menu_items the merchant controls.
Route::middleware('web')->group(function () {
    Route::get('/blog', [StorefrontBlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{post:slug}', [StorefrontBlogController::class, 'show'])->name('blog.show');
});
