<?php

use Extensions\PaymentBankTransfer\Http\Controllers\BankAccountController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// Route names frozen (admin.bank-accounts.*); only the owner moved with
// the conversion. Same URLs as before under extensions/payments.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/payments')
    ->name('admin.')
    ->group(function () {
        Route::get('/bank-accounts/create', [BankAccountController::class, 'create'])->name('bank-accounts.create');
        Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
        Route::get('/bank-accounts/{bankAccount}/edit', [BankAccountController::class, 'edit'])->name('bank-accounts.edit');
        Route::put('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
        Route::delete('/bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');
    });
