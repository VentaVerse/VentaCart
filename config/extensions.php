<?php

return [

    /*
    |--------------------------------------------------------------------
    | Extensions Path
    |--------------------------------------------------------------------
    |
    | Where ExtensionManager scans for extension.json manifests
    | (extensions/{name}/extension.json). Overridden in tests to point
    | at tests/fixtures/extensions so kernel tests never touch real
    | extension folders.
    |
    */

    'path' => base_path('extensions'),

    /*
    |--------------------------------------------------------------------
    | Themes Path and Published Package Builds
    |--------------------------------------------------------------------
    |
    | Where themes live (resources/themes/{slug}/theme.json), and where a
    | package's build/ folder is copied so the browser can load it
    | (public/packages/{name}, served at /packages/{name}). Overridden in
    | tests so they never write to the real folders.
    |
    */

    'themes_path' => resource_path('themes'),

    'public_path' => public_path('packages'),

    /*
    |--------------------------------------------------------------------
    | Package uploads
    |--------------------------------------------------------------------
    |
    | Limits for a theme or extension uploaded as a .zip in the admin
    | (Extensions > Upload). The upload itself is also capped by PHP's
    | upload_max_filesize and post_max_size, whichever is lower. The
    | archive is refused before it is unpacked when it holds more entries
    | than max_files, a file larger than max_file_mb, or more than
    | max_unpacked_mb in all.
    |
    | Installing code from the admin needs the web server to be able to
    | write to the themes, extensions and public/packages folders. Who may
    | upload is the extensions.upload permission.
    |
    */

    'upload' => [
        'max_size_mb' => (int) env('PACKAGE_UPLOAD_MAX_MB', 64),
        'max_files' => (int) env('PACKAGE_UPLOAD_MAX_FILES', 5000),
        'max_file_mb' => (int) env('PACKAGE_UPLOAD_MAX_FILE_MB', 32),
        'max_unpacked_mb' => (int) env('PACKAGE_UPLOAD_MAX_UNPACKED_MB', 256),
        // Uploads one admin may send per minute.
        'per_minute' => (int) env('PACKAGE_UPLOAD_PER_MINUTE', 5),
        // Seconds PHP may spend on one upload; the install step runs past
        // the usual request limit so a slow disk or migration can finish.
        'max_seconds' => (int) env('PACKAGE_UPLOAD_MAX_SECONDS', 900),
        // The lock every package change takes (App\Support\PackageLock),
        // and how long a change waits for another one to finish.
        'lock_file' => env('PACKAGE_LOCK_FILE', storage_path('framework/package-changes.lock')),
        'lock_wait_seconds' => (int) env('PACKAGE_LOCK_WAIT_SECONDS', 10),
    ],

];
