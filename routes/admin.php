<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin.session.cap'])
    ->prefix(config('admin.prefix', 'admin'))
    ->name('admin.')
    ->group(function () {
        require __DIR__.'/admin/dashboard.php';
        require __DIR__.'/admin/sales.php';
        require __DIR__.'/admin/catalog.php';
        require __DIR__.'/admin/customers.php';
        require __DIR__.'/admin/content.php';
        require __DIR__.'/admin/storefront.php';
        require __DIR__.'/admin/marketing.php';
        require __DIR__.'/admin/extensions.php';
        require __DIR__.'/admin/settings.php';
        require __DIR__.'/admin/redirects.php';
    });
