<?php

use Extensions\PhilippineAddress\Http\Controllers\PhAddressLookupController;
use Illuminate\Support\Facades\Route;

// Storefront PH address lookup (names must match storefront.address.* group used in cart/checkout templates)
Route::middleware(['web'])->prefix('address-lookup')->name('storefront.address.')->group(function () {
    Route::get('/ph-provinces', [PhAddressLookupController::class, 'provinces'])->name('ph-provinces');
    Route::get('/ph-cities', [PhAddressLookupController::class, 'cities'])->name('ph-cities');
    Route::get('/ph-districts', [PhAddressLookupController::class, 'districts'])->name('ph-districts');
    Route::get('/ph-barangays', [PhAddressLookupController::class, 'barangays'])->name('ph-barangays');
});

// Admin PH address lookup
Route::middleware(['web', 'auth'])->prefix('admin/address')->group(function () {
    Route::get('/ph-provinces', [PhAddressLookupController::class, 'provinces'])->name('admin.ph.address.provinces');
    Route::get('/ph-cities', [PhAddressLookupController::class, 'cities'])->name('admin.ph.address.cities');
    Route::get('/ph-districts', [PhAddressLookupController::class, 'districts'])->name('admin.ph.address.districts');
    Route::get('/ph-barangays', [PhAddressLookupController::class, 'barangays'])->name('admin.ph.address.barangays');
});
