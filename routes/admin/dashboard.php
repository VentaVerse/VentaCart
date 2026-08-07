<?php

use App\Http\Controllers\Admin\AddressLookupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaletteController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('dashboard/palette-search', [PaletteController::class, 'search'])->name('palette.search');

if (app()->environment(['local', 'testing'])) {
    Route::view('dashboard/ui-kit', 'admin.ui-kit')->name('ui-kit');
}

// Address Lookups — shared admin-wide utility consumed by orders, customers,
// and localization forms via the named route (route() calls, never a
// hardcoded URL), so relocating it here is safe regardless of who uses it.
Route::get('/dashboard/address/countries', [AddressLookupController::class, 'countries'])->name('address.countries');
Route::get('/dashboard/address/zones', [AddressLookupController::class, 'zones'])->name('address.zones');
// Deep-address lookups (e.g. PH city/barangay) are read on-demand from bundled files; routes added when the deep tier is enabled.

// Auth/account chrome — top-level per the grammar map ({p}/profile), not
// nested under any section.
Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
