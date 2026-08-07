<?php

use Illuminate\Support\Facades\Route;

/**
 * Trivial probe route, mounted by DemoServiceProvider::boot() via
 * loadRoutesFrom(). Exists solely so ExtensionLifecycleTest's Task-2
 * HTTP-level tests have something observable to assert on beyond the
 * 'extensions.demo.booted' container marker: whether this named route is
 * registered proves whether the provider's boot() actually ran for the
 * current request (i.e. whether ExtensionManager::bootEnabled() decided to
 * boot the demo extension), the same signal a real extension's admin/API
 * routes would give an operator.
 */
Route::get('/__demo-extension-probe', fn () => 'demo-extension-ok')->name('extensions.demo.probe');
