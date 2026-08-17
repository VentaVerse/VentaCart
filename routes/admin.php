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
        require __DIR__.'/admin/marketing.php';
        require __DIR__.'/admin/extensions.php';
        require __DIR__.'/admin/settings.php';
        require __DIR__.'/admin/redirects.php';
    });

// Legacy banners URLs were hardcoded to a literal 'admin/banners' prefix —
// the exact prefix bug this spec fixes (see extensions/banners/routes/web.php,
// now mounted under the *configured* admin prefix at extensions/banners).
// Because the old URL ignored config('admin.prefix') entirely, this redirect
// lives outside the configured-prefix group above, registered at the same
// literal path the old routes used. {path?} + a permissive where() catches
// every sub-page (group edit, slides, etc.); the query string is forwarded
// so any bookmarked filter/pagination survives the redirect.
Route::middleware(['web', 'auth'])
    ->get('admin/banners/{path?}', function (?string $path = null) {
        $destination = '/'.trim(config('admin.prefix', 'admin').'/extensions/banners/'.($path ?? ''), '/');

        if ($qs = request()->getQueryString()) {
            $destination .= '?'.$qs;
        }

        return redirect($destination, 301);
    })
    ->where('path', '.*')
    ->name('legacy-banners-redirect');
