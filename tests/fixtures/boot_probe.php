<?php

/**
 * Standalone script (NOT run by PHPUnit's own process): boots the real
 * application (bootstrap/app.php) as a genuinely separate PHP process
 * against a caller-specified SQLite file, and sends one real request
 * through the full HTTP kernel.
 *
 * Why this has to be a separate process at all: the bug this exists to
 * reproduce/guard (ExtensionServiceProvider::register() -> bootEnabled() ->
 * registry() crashing on Eloquent statics before Model::$resolver is wired)
 * can ONLY happen when the `extensions` table already exists at the moment
 * providers register — i.e. a normal, already-migrated deployment. Every
 * PHPUnit test in this suite uses RefreshDatabase, which structurally
 * cannot produce that condition: RefreshDatabase swaps in the migrated
 * in-memory connection in setUpTraits(), which runs AFTER the application
 * (and thus every provider's register()) has already finished booting. A
 * fresh, independent process booting against a file that is ALREADY
 * migrated before boot starts is the only way to reproduce the real
 * ordering. See tests/Feature/AdminV2/ExtensionKernelBootSafetyTest.php,
 * which shells out to this script.
 *
 * Usage: php boot_probe.php /path/to/db.sqlite
 * Prints "STATUS:<http-status>" on a completed response, or
 * "FATAL:<ExceptionClass>: <message>" if bootstrap itself throws before a
 * response can be produced (which is exactly what an unfixed registry()
 * does — a register()-phase crash aborts the entire application boot).
 */

$dbPath = $argv[1] ?? null;

if (! $dbPath) {
    fwrite(STDERR, "Usage: boot_probe.php <sqlite-path>\n");
    exit(2);
}

// Set BEFORE requiring anything: Laravel's env() helper reads from the
// process environment first, and Dotenv (used by LoadEnvironmentVariables)
// does not overwrite variables that are already set — so these win over
// whatever the real .env has, without needing to touch that file.
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $dbPath;
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = $dbPath;

$basePath = dirname(__DIR__, 2);

require $basePath . '/vendor/autoload.php';

try {
    $app = require $basePath . '/bootstrap/app.php';

    /** @var \Illuminate\Contracts\Http\Kernel $kernel */
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

    $request = \Illuminate\Http\Request::create('/up', 'GET');
    $response = $kernel->handle($request);

    echo 'STATUS:' . $response->getStatusCode() . "\n";

    $kernel->terminate($request, $response);
} catch (\Throwable $e) {
    echo 'FATAL:' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(1);
}
