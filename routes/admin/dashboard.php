<?php

use App\Http\Controllers\Admin\AddressLookupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaletteController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('dashboard/palette-search', [PaletteController::class, 'search'])->name('palette.search');

Route::get('dashboard/sales-series', [DashboardController::class, 'salesSeries'])->name('dashboard.sales-series');

if (app()->environment(['local', 'testing'])) {
    Route::view('dashboard/ui-kit', 'admin.ui-kit')->name('ui-kit');
}

// Address Lookups — shared admin-wide utility consumed by orders, customers,
// and localization forms via the named route (route() calls, never a
// hardcoded URL), so relocating it here is safe regardless of who uses it.
Route::get('/dashboard/address/countries', [AddressLookupController::class, 'countries'])->name('address.countries');
Route::get('/dashboard/address/zones', [AddressLookupController::class, 'zones'])->name('address.zones');
// Deep-address lookups (e.g. PH city/barangay) are read on-demand from bundled
// files by App\Services\Address\DeepAddressData. This is the admin's own
// authenticated endpoint over that service, mirroring the public storefront
// route (storefront.address.deep) one-for-one in country/level/parent and in
// response shape. Admin forms bind here so no admin screen ever depends on a
// public route.
Route::get('/dashboard/address/deep/{country}/{level}', [AddressLookupController::class, 'deep'])
    ->where(['country' => '[A-Za-z]{2}', 'level' => '[a-z_]+'])
    ->name('address.deep');

// Auth/account chrome — top-level per the grammar map ({p}/profile), not
// nested under any section.
Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
