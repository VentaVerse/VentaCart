<?php

use Extensions\PhilippineAddress\Http\Controllers\PhAddressLookupController;
use Illuminate\Support\Facades\Route;

// Storefront PH address lookup
Route::middleware(['web'])->prefix('address-lookup')->group(function () {
    Route::get('/ph-provinces', [PhAddressLookupController::class, 'provinces'])->name('ph.address.provinces');
    Route::get('/ph-cities', [PhAddressLookupController::class, 'cities'])->name('ph.address.cities');
    Route::get('/ph-districts', [PhAddressLookupController::class, 'districts'])->name('ph.address.districts');
    Route::get('/ph-barangays', [PhAddressLookupController::class, 'barangays'])->name('ph.address.barangays');
});

// Admin PH address lookup
Route::middleware(['web', 'auth'])->prefix('admin/address')->group(function () {
    Route::get('/ph-provinces', [PhAddressLookupController::class, 'provinces'])->name('admin.ph.address.provinces');
    Route::get('/ph-cities', [PhAddressLookupController::class, 'cities'])->name('admin.ph.address.cities');
    Route::get('/ph-districts', [PhAddressLookupController::class, 'districts'])->name('admin.ph.address.districts');
    Route::get('/ph-barangays', [PhAddressLookupController::class, 'barangays'])->name('admin.ph.address.barangays');
});
