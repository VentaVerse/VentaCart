<?php

use Illuminate\Support\Facades\Route;
use Themes\Xelga\Http\Controllers\ExtensionStylesheetController;
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

// An extension's stylesheet as it ships, for a store whose build lacks it.
// Addressed by a fingerprint of the file and cached for a year; no session.
Route::get('/xelga/extensions/{extension}/{hash}.css', [ExtensionStylesheetController::class, 'show'])
    ->where(['extension' => '[a-z][a-z0-9-]{0,39}', 'hash' => '[0-9a-f]{12}'])
    ->name('xelga.extension-css');
