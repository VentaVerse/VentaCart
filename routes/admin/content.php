<?php

use App\Http\Controllers\Admin\Content\PageController;
use Illuminate\Support\Facades\Route;

Route::prefix('content')->group(function () {
    Route::resource('pages', PageController::class)->except(['show']);
    // Blog moved to the blog extension (extensions/blog/routes/web.php).
});
