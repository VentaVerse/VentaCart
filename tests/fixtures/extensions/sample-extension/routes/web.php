<?php

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Extensions\SampleExtension\Http\SampleAccountController;
use Tests\Fixtures\Extensions\SampleExtension\Http\SamplePageController;

Route::middleware('web')->group(function () {
    Route::get('/sample-extension/things/{slug}', [SamplePageController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('sample-extension.page.show');

    Route::get('/account/sample-things', [SampleAccountController::class, 'index'])
        ->middleware(['customer.auth', 'noindex'])
        ->name('sample-extension.account');
});
