<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::prefix('customers')->group(function () {
    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::get('/customers/{customer}/impersonate', [ImpersonationController::class, 'start'])->name('customers.impersonate');
    Route::get('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    // Route name stays 'customer-groups.*' (frozen); the URI entity segment
    // is 'groups' per the grammar map ({p}/customers/customers · /groups).
    // ->parameters() keeps the bound variable {customerGroup} matching
    // CustomerGroupController's method signatures; ->names() keeps the
    // route name prefix 'customer-groups' instead of the URI-derived
    // 'groups'.
    Route::resource('groups', CustomerGroupController::class)
        ->except(['show'])
        ->parameters(['groups' => 'customerGroup'])
        ->names('customer-groups');
});
