<?php

use Extensions\SmsUnisms\Http\Controllers\UniSmsController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Mounted under the extensions/{name} convention every other extension uses,
// so the gateway is found where a merchant already looks for its settings.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/sms-unisms')
    ->name('admin.extensions.unisms.')
    ->group(function () {
        Route::get('/', [UniSmsController::class, 'index'])->name('index');
        Route::post('/', [UniSmsController::class, 'update'])->name('update');
        Route::post('/test', [UniSmsController::class, 'test'])->name('test');
        Route::post('/send-test', [UniSmsController::class, 'sendTest'])->name('send-test');
    });
