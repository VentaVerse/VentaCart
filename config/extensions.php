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
    */

    'upload' => [
        'max_size_mb' => (int) env('PACKAGE_UPLOAD_MAX_MB', 64),
        'max_files' => (int) env('PACKAGE_UPLOAD_MAX_FILES', 5000),
        'max_file_mb' => (int) env('PACKAGE_UPLOAD_MAX_FILE_MB', 32),
        'max_unpacked_mb' => (int) env('PACKAGE_UPLOAD_MAX_UNPACKED_MB', 256),
        // Uploads one admin may send per minute.
        'per_minute' => (int) env('PACKAGE_UPLOAD_PER_MINUTE', 5),
    ],

];
