<?php

use Extensions\PhilippineAddress\Http\Controllers\PhAddressLookupController;
use Extensions\PhilippineAddress\Http\Controllers\PhAddressSettingsController;
use Extensions\PhilippineAddress\Http\Controllers\PhGeoZoneController;
use Illuminate\Support\Facades\Route;

// Storefront PH address lookup (names must match storefront.address.* group used in cart/checkout templates)
Route::middleware(['web'])->prefix('address-lookup')->name('storefront.address.')->group(function () {
    Route::get('/ph-provinces', [PhAddressLookupController::class, 'provinces'])->name('ph-provinces');
    Route::get('/ph-cities', [PhAddressLookupController::class, 'cities'])->name('ph-cities');
    Route::get('/ph-districts', [PhAddressLookupController::class, 'districts'])->name('ph-districts');
    Route::get('/ph-barangays', [PhAddressLookupController::class, 'barangays'])->name('ph-barangays');
});

// Admin PH address lookup (names must match admin views: admin.address.ph-*)
Route::middleware(['web', 'auth'])->prefix('admin/address')->name('admin.address.')->group(function () {
    Route::get('/ph-provinces', [PhAddressLookupController::class, 'provinces'])->name('ph-provinces');
    Route::get('/ph-cities', [PhAddressLookupController::class, 'cities'])->name('ph-cities');
    Route::get('/ph-districts', [PhAddressLookupController::class, 'districts'])->name('ph-districts');
    Route::get('/ph-barangays', [PhAddressLookupController::class, 'barangays'])->name('ph-barangays');
});

// Admin PH Address extension settings
Route::middleware(['web', 'auth'])->prefix('admin/ph-address')->name('admin.ph-address.')->group(function () {
    Route::get('/', [PhAddressSettingsController::class, 'index'])->name('settings');
    Route::post('/toggle', [PhAddressSettingsController::class, 'toggle'])->name('toggle');

    // PH Geo Zones
    Route::get('/geo-zones', [PhGeoZoneController::class, 'index'])->name('geo-zones.index');
    Route::get('/geo-zones/create', [PhGeoZoneController::class, 'create'])->name('geo-zones.create');
    Route::post('/geo-zones', [PhGeoZoneController::class, 'store'])->name('geo-zones.store');
    Route::get('/geo-zones/{geoZone}/edit', [PhGeoZoneController::class, 'edit'])->name('geo-zones.edit');
    Route::put('/geo-zones/{geoZone}', [PhGeoZoneController::class, 'update'])->name('geo-zones.update');
    Route::delete('/geo-zones/{geoZone}', [PhGeoZoneController::class, 'destroy'])->name('geo-zones.destroy');
});
