<?php

/**
 * Runs package uploads in a process of its own, against a file-backed
 * SQLite database and scratch folders, so a test can watch what happens
 * when PHP dies part way through an install (which no catch or finally
 * survives, and which would end the test run itself).
 *
 *   package_upload_probe.php <sqlite-file> <scratch-folder> <zip> [<zip> ...]
 *
 * Each zip is uploaded in turn; "UPLOADED:<result>" or "REFUSED:<message>"
 * is printed after each. A zip that kills the process prints nothing more.
 */

[$script, $database, $scratch] = $argv + [null, null, null];
$zips = array_slice($argv, 3);

if (! $database || ! $scratch || $zips === []) {
    fwrite(STDERR, "Usage: package_upload_probe.php <sqlite-file> <scratch-folder> <zip> [<zip> ...]\n");
    exit(2);
}

foreach ([
    'APP_ENV' => 'local',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'LOG_CHANNEL' => 'single',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$basePath = dirname(__DIR__, 2);
require $basePath . '/vendor/autoload.php';

$app = require $basePath . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config([
    'extensions.path' => $scratch . '/extensions',
    'extensions.themes_path' => $scratch . '/themes',
    'extensions.public_path' => $scratch . '/public-packages',
    'extensions.upload.lock_file' => $scratch . '/package-changes.lock',
    'extensions.upload.lock_wait_seconds' => 0,
    'logging.channels.single.path' => $scratch . '/probe.log',
]);

if (! Illuminate\Support\Facades\Schema::hasTable('package_uploads')) {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
}

foreach ($zips as $zip) {
    try {
        $record = $app->make(App\Services\PackageUploader::class)
            ->upload(new Illuminate\Http\UploadedFile($zip, basename($zip), 'application/zip', null, true), null);
        echo 'UPLOADED:' . $record->result . "\n";
    } catch (App\Exceptions\PackageRefused $e) {
        echo 'REFUSED:' . $e->getMessage() . "\n";
    }
}
