<?php

use Illuminate\Support\Facades\Route;
use Themes\Xelga\Http\Controllers\TokensController;

// The tokens stylesheet. The published one is addressed by the settings'
// published version and cached for a year: a publish changes the address.
// It needs no session, so it carries no cookies.
Route::get('/xelga/tokens/{version}.css', [TokensController::class, 'published'])
    ->where('version', '[0-9]{1,9}')
    ->name('xelga.tokens');

// The draft, only inside an open preview window, never cached.
Route::middleware('web')
    ->get('/xelga/tokens/draft.css', [TokensController::class, 'draft'])
    ->name('xelga.tokens.draft');
