<?php

namespace Tests\Feature\AdminV2;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Regression coverage for a CRITICAL boot-safety bug found in code review:
 * ExtensionServiceProvider::register() -> ExtensionManager::bootEnabled()
 * -> registry() used to call Extension::all() (an Eloquent static). Eloquent
 * needs Model::$resolver, which Illuminate\Database\DatabaseServiceProvider
 * only wires in its boot() — not its register(). Since our own provider's
 * register() runs before that, the very first time the `extensions` table
 * genuinely exists at boot time (i.e. every real, already-migrated
 * deployment — the normal case, not an edge case), every single request
 * would throw "Call to a member function connection() on null" during
 * application bootstrap and 500.
 *
 * Deliberately does NOT extend Tests\TestCase / use RefreshDatabase: that
 * harness cannot reproduce the bug at all (see boot_probe.php's docblock for
 * why the DB-ready timing is inverted there) and would boot Laravel INSIDE
 * this test process, contaminating the very thing under test. This is a
 * plain PHPUnit test that shells out to a genuinely separate PHP process.
 */
class ExtensionKernelBootSafetyTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbFile = sys_get_temp_dir() . '/venta-ext-kernel-boot-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbFile)) {
            unlink($this->dbFile);
        }

        parent::tearDown();
    }

    public function test_second_app_boot_survives_a_present_populated_extensions_table(): void
    {
        $this->seedMigratedDatabase();

        $output = $this->bootSecondApplication();

        $this->assertStringStartsWith(
            'STATUS:200',
            $output,
            "Booting a second, genuinely separate Application process against an already-migrated `extensions` table (with an enabled row) must succeed — this is what every real request looks like post-deploy.\nProbe output:\n{$output}"
        );
    }

    /**
     * Creates a file-backed SQLite DB with the two tables reachable from
     * ExtensionServiceProvider::register() (`extensions`, read by
     * registry(); `cache`, read by registry()'s Cache::has()/forever() since
     * this repo's real .env sets CACHE_STORE=database) and seeds an
     * enabled row — mirroring a real, already-migrated, live deployment.
     * Raw PDO on purpose: this must not depend on Laravel booting
     * successfully, since proving Laravel boots successfully is the whole
     * point of the test.
     */
    private function seedMigratedDatabase(): void
    {
        $pdo = new \PDO('sqlite:' . $this->dbFile);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec(<<<'SQL'
            CREATE TABLE extensions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR NOT NULL UNIQUE,
                version VARCHAR NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                installed_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
            SQL);

        $pdo->exec(<<<'SQL'
            INSERT INTO extensions (name, version, enabled, installed_at, created_at, updated_at)
            VALUES ('banners', '1.0.0', 1, datetime('now'), datetime('now'), datetime('now'))
            SQL);

        // Laravel's default database cache store schema (0001_01_01_000001_create_cache_table.php).
        $pdo->exec(<<<'SQL'
            CREATE TABLE cache (
                "key" VARCHAR NOT NULL PRIMARY KEY,
                value TEXT NOT NULL,
                expiration INTEGER NOT NULL
            )
            SQL);
    }

    private function bootSecondApplication(): string
    {
        $phpBinary = (new PhpExecutableFinder())->find() ?: 'php';
        $script = dirname(__DIR__, 2) . '/fixtures/boot_probe.php';

        $process = new Process([$phpBinary, $script, $this->dbFile]);
        $process->setTimeout(30);
        $process->run();

        return trim($process->getOutput() . $process->getErrorOutput());
    }
}
