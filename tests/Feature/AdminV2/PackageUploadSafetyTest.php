<?php

namespace Tests\Feature\AdminV2;

use App\Exceptions\PackageRefused;
use App\Models\Extension;
use App\Models\PackageUpload;
use App\Services\ExtensionManager;
use App\Services\PackageUploader;
use App\Services\ThemeService;
use App\Support\PackageArchive;
use App\Support\PackageAssets;
use App\Support\PackageLock;
use App\Support\PackageVite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsPackageUploads;
use Tests\TestCase;
use ZipArchive;

/**
 * The ways an upload can go wrong after the archive itself passed: a
 * record that must survive a strict database, an install that fails or is
 * cut short, packages that reach for names, namespaces or migrations that
 * belong to someone else, and builds that would put runnable files in the
 * public folder.
 */
class PackageUploadSafetyTest extends TestCase
{
    use BuildsPackageUploads;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPackageScratch();
    }

    private function assertNothingInstalled(): void
    {
        $this->assertDirectoryDoesNotExist($this->themePath());
        $this->assertNull(Extension::where('name', 'theme-' . self::SLUG)->first());
        $this->assertSame([], glob($this->themesRoot . '/.upload-*') ?: []);
        $this->assertSame([], glob($this->extensionsRoot . '/.upload-*') ?: []);
    }

    private function themeVersionOnDisk(): ?string
    {
        return json_decode((string) @file_get_contents($this->themePath('theme.json')), true)['version'] ?? null;
    }

    /** A migration whose up() runs $body. */
    private function migrationRunning(string $body): string
    {
        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;

        return new class extends Migration
        {
            public function up(): void
            {
                {$body}
            }

            public function down(): void
            {
            }
        };
        PHP;
    }

    private function extensionZip(string $name, array $manifest = [], array $files = []): string
    {
        $entries = [
            "{$name}/extension.json" => json_encode($manifest + ['name' => $name, 'title' => 'Sample tools', 'version' => '1.0.0']),
        ];
        foreach ($files as $path => $contents) {
            $entries["{$name}/{$path}"] = $contents;
        }

        return $this->zip($entries);
    }

    /** An extension that sits on disk as if the store shipped it. */
    private function extensionOnDisk(string $name, array $manifest = [], array $files = []): string
    {
        $path = $this->extensionsRoot . '/' . $name;
        File::ensureDirectoryExists($path);
        file_put_contents($path . '/extension.json', json_encode($manifest + ['name' => $name, 'title' => 'Shipped ' . $name, 'version' => '1.0.0']));
        foreach ($files as $file => $contents) {
            File::ensureDirectoryExists(dirname($path . '/' . $file));
            file_put_contents($path . '/' . $file, $contents);
        }
        app(ExtensionManager::class)->rescan();

        return $path;
    }

    /** Changes bytes of a zip in place: every $from (same length as $to) becomes $to. */
    private function patchZip(string $zip, string $from, string $to): void
    {
        $this->assertSame(strlen($from), strlen($to));
        file_put_contents($zip, str_replace($from, $to, (string) file_get_contents($zip)));
    }

    // The record of an upload ---------------------------------------------

    public function test_a_version_longer_than_its_column_is_refused(): void
    {
        $version = '1.0.0-' . str_repeat('a', 35);
        $this->assertSame(41, strlen($version));
        $this->assertMatchesRegularExpression(PackageArchive::VERSION_PATTERN, $version);

        $this->upload($this->themeZip($version));

        $this->assertRefused('at most 40 characters');
        $this->assertNothingInstalled();
    }

    public function test_a_huge_name_installs_and_every_value_fits_its_column(): void
    {
        $version = '1.0.0-' . str_repeat('b', 34);
        $this->assertSame(40, strlen($version));

        $this->upload($this->themeZip($version, [], self::SLUG, fn (array $meta) => ['name' => 'Upload Sample ' . str_repeat('x', 70000)] + $meta), str_repeat('f', 300) . '.zip');

        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        $record = PackageUpload::sole();
        $this->assertSame(PackageUpload::INSTALLED, $record->result);
        $this->assertTrue(PackageUpload::placed('theme-' . self::SLUG));

        foreach (PackageUpload::LENGTHS as $column => $max) {
            $value = $record->getRawOriginal($column);
            if ($value !== null) {
                $this->assertLessThanOrEqual($max, mb_strlen((string) $value), "{$column} holds more than its column allows.");
            }
        }
    }

    public function test_the_record_lengths_never_exceed_the_columns_on_mysql(): void
    {
        // The suite runs on SQLite, which ignores string lengths. Compile the
        // migration with the MySQL grammar instead and read the lengths it
        // declares.
        config(['database.connections.mysql-ddl' => ['driver' => 'mysql', 'host' => '192.0.2.1', 'database' => 'demo', 'username' => 'demo', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '']]);
        $connection = DB::connection('mysql-ddl');
        $connection->useDefaultSchemaGrammar();
        $migration = require database_path('migrations/2026_10_26_100000_create_package_uploads.php');
        $schema = Schema::getFacadeRoot();

        try {
            Schema::swap($connection->getSchemaBuilder());
            $queries = $connection->pretend(fn () => $migration->up());
        } finally {
            Schema::swap($schema);
            DB::purge('mysql-ddl');
        }

        $create = $queries[0]['query'];
        foreach (PackageUpload::LENGTHS as $column => $max) {
            if ($column === 'message') {
                $this->assertStringContainsString('`message` text', $create);
                // utf8mb4 text holds 65,535 bytes, at most four per character.
                $this->assertLessThanOrEqual(intdiv(65535, 4), $max);

                continue;
            }

            $this->assertMatchesRegularExpression('/`' . $column . '` varchar\((\d+)\)/', $create);
            preg_match('/`' . $column . '` varchar\((\d+)\)/', $create, $m);
            $this->assertLessThanOrEqual((int) $m[1], $max, "PackageUpload::LENGTHS['{$column}'] is longer than the column.");
        }

        $this->assertLessThanOrEqual(PackageUpload::LENGTHS['version'], PackageArchive::VERSION_MAX);
    }

    public function test_the_upload_is_recorded_as_pending_before_anything_is_installed(): void
    {
        $marker = $this->scratch . '/seen-result.txt';

        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/database/migrations/2026_01_02_000000_note_the_record.php' => $this->migrationRunning(
                "file_put_contents('{$marker}', (string) \\Illuminate\\Support\\Facades\\DB::table('package_uploads')->orderByDesc('id')->value('result'));"
            ),
        ]));

        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        $this->assertSame(PackageUpload::PENDING, file_get_contents($marker));
        $this->assertSame(PackageUpload::INSTALLED, PackageUpload::sole()->result);
    }

    public function test_an_install_that_worked_is_recorded_as_installed_when_a_later_step_fails(): void
    {
        $this->app->instance(ThemeService::class, new class extends ThemeService
        {
            public function clearCache(): void
            {
                throw new \RuntimeException('The cache store is down.');
            }
        });

        $this->upload($this->themeZip('1.0.0'));

        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        $this->assertSame(PackageUpload::INSTALLED, PackageUpload::sole()->result);
        $this->assertTrue(PackageUpload::placed('theme-' . self::SLUG));
        $this->assertSame('1.0.0', $this->themeVersionOnDisk());
    }

    // Failed and interrupted installs --------------------------------------

    public function test_a_failed_update_puts_back_the_files_tables_registry_and_build(): void
    {
        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/build/manifest.json' => json_encode(['resources/themes/upload-sample/css/extra.css' => ['file' => 'assets/extra-old.css', 'isEntry' => true]]),
            self::SLUG . '/build/assets/extra-old.css' => 'body{}',
        ]));
        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        DB::table('upload_sample_notes')->insert(['body' => 'Kept']);

        $this->upload($this->themeZip('1.1.0', [
            self::SLUG . '/database/migrations/2026_02_01_000000_create_upload_sample_tags.php' => $this->migration('upload_sample_tags'),
            self::SLUG . '/database/migrations/2026_02_02_000000_break.php' => $this->migrationRunning("throw new \\RuntimeException('This migration fails.');"),
            self::SLUG . '/views/partials/added-in-update.blade.php' => '<div></div>',
            self::SLUG . '/build/manifest.json' => json_encode(['resources/themes/upload-sample/css/extra.css' => ['file' => 'assets/extra-new.css', 'isEntry' => true]]),
            self::SLUG . '/build/assets/extra-new.css' => 'main{}',
        ]));

        $result = $this->outcome();
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('put back the files, database tables and settings', $result['message']);

        $this->assertSame('1.0.0', $this->themeVersionOnDisk());
        $this->assertFileDoesNotExist($this->themePath('views/partials/added-in-update.blade.php'));
        $this->assertSame('1.0.0', Extension::where('name', 'theme-' . self::SLUG)->value('version'));
        $this->assertFalse(Schema::hasTable('upload_sample_tags'));
        $this->assertFalse(DB::table('migrations')->where('migration', '2026_02_01_000000_create_upload_sample_tags')->exists());
        $this->assertSame('Kept', DB::table('upload_sample_notes')->value('body'));

        $published = PackageAssets::publicPath('theme-' . self::SLUG);
        $this->assertFileExists($published . '/assets/extra-old.css');
        $this->assertFileDoesNotExist($published . '/assets/extra-new.css');

        $this->assertSame(PackageUpload::FAILED, PackageUpload::latest('id')->value('result'));
        $this->assertTrue(PackageUpload::placed('theme-' . self::SLUG));
        $this->assertSame([], glob($this->themesRoot . '/.upload-*') ?: []);
    }

    public function test_a_failed_first_install_leaves_nothing_behind(): void
    {
        $this->upload($this->extensionZip('sample-tools', [], [
            'database/migrations/2026_01_01_000000_create_sample_tools_items.php' => $this->migration('sample_tools_items'),
            'database/migrations/2026_01_02_000000_break.php' => $this->migrationRunning("throw new \\RuntimeException('This migration fails.');"),
        ]));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertDirectoryDoesNotExist($this->extensionsRoot . '/sample-tools');
        $this->assertNull(Extension::where('name', 'sample-tools')->first());
        $this->assertFalse(Schema::hasTable('sample_tools_items'));
        $this->assertSame(PackageUpload::FAILED, PackageUpload::sole()->result);
        $this->assertFalse(PackageUpload::placed('sample-tools'));
        $this->assertSame([], glob($this->extensionsRoot . '/.upload-*') ?: []);
    }

    public function test_when_the_previous_files_cannot_be_put_back_the_message_says_so_and_keeps_them(): void
    {
        $this->upload($this->themeZip('1.0.0'));

        // The migration takes away write access to the themes folder, so
        // the failed update cannot be moved out of the way.
        $root = $this->themesRoot;
        $this->upload($this->themeZip('1.1.0', [
            self::SLUG . '/database/migrations/2026_02_02_000000_break.php' => $this->migrationRunning("chmod('{$root}', 0555); throw new \\RuntimeException('This migration fails.');"),
        ]));

        try {
            $result = $this->outcome();
            $this->assertFalse($result['ok']);
            $this->assertStringContainsString('could not put the previous files back', $result['message']);
            $this->assertStringNotContainsString($root, $result['message']);

            $kept = glob($root . '/.upload-*') ?: [];
            $this->assertCount(1, $kept);
            $this->assertSame('1.0.0', json_decode((string) file_get_contents($kept[0] . '/previous/theme.json'), true)['version']);
            $this->assertSame(PackageUpload::FAILED, PackageUpload::latest('id')->value('result'));
        } finally {
            chmod($root, 0755);
        }
    }

    public function test_an_upload_a_killed_process_left_part_way_is_undone_on_the_next_visit(): void
    {
        $this->upload($this->themeZip('1.0.0'));
        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');

        // What a process killed in the middle of an update leaves: the new
        // files in place, the old ones aside, the registry already bumped,
        // and the record still pending.
        $work = $this->themesRoot . '/.upload-killedupload';
        File::ensureDirectoryExists($work . '/unpacked');
        rename($this->themePath(), $work . '/previous');
        File::copyDirectory($work . '/previous', $this->themePath());
        file_put_contents($this->themePath('theme.json'), json_encode(['version' => '1.1.0'] + json_decode((string) file_get_contents($work . '/previous/theme.json'), true)));
        Extension::where('name', 'theme-' . self::SLUG)->update(['version' => '1.1.0']);
        $pending = PackageUpload::create(['filename' => 'update.zip', 'size' => 1, 'sha256' => str_repeat('0', 64), 'result' => PackageUpload::PENDING, 'package' => 'theme-' . self::SLUG]);
        file_put_contents($work . '/journal.json', json_encode([
            'record' => $pending->id,
            'stage' => 'installing',
            'result' => PackageUpload::UPDATED,
            'package' => 'theme-' . self::SLUG,
            'title' => 'Upload Sample',
            'target' => $this->themePath(),
            'work' => $work,
            'staged' => $work . '/unpacked/' . self::SLUG,
            'had_previous' => true,
            'names' => ['theme-' . self::SLUG],
            'registry' => ['theme-' . self::SLUG => ['version' => '1.0.0', 'enabled' => true, 'installed_at' => now()->toDateTimeString()]],
            'batch' => app(ExtensionManager::class)->lastMigrationBatch(),
        ]));
        app(ExtensionManager::class)->forgetRegistry();

        $this->get(route('admin.extensions.upload'))->assertOk()->assertSee('stopped part way');

        $this->assertSame('1.0.0', $this->themeVersionOnDisk());
        $this->assertSame('1.0.0', Extension::where('name', 'theme-' . self::SLUG)->value('version'));
        $this->assertSame(PackageUpload::FAILED, $pending->fresh()->result);
        $this->assertDirectoryDoesNotExist($work);
    }

    public function test_a_pending_record_with_nothing_on_disk_is_closed(): void
    {
        $pending = PackageUpload::create(['filename' => 'x.zip', 'size' => 1, 'sha256' => str_repeat('0', 64), 'result' => PackageUpload::PENDING]);
        File::ensureDirectoryExists($this->extensionsRoot . '/.upload-halfunpacked/unpacked/x');

        $this->get(route('admin.extensions.upload'))->assertOk();

        $this->assertSame(PackageUpload::FAILED, $pending->fresh()->result);
        $this->assertDirectoryDoesNotExist($this->extensionsRoot . '/.upload-halfunpacked');
    }

    public function test_a_journal_pointing_outside_its_folder_is_never_followed(): void
    {
        $elsewhere = $this->scratch . '/elsewhere';
        File::ensureDirectoryExists($elsewhere);
        file_put_contents($elsewhere . '/keep.txt', 'x');
        $work = $this->themesRoot . '/.upload-forgedjournal';
        File::ensureDirectoryExists($work);
        file_put_contents($work . '/journal.json', json_encode(['stage' => 'installing', 'target' => $elsewhere, 'had_previous' => false, 'names' => []]));

        $this->get(route('admin.extensions.upload'))->assertOk();

        $this->assertFileExists($elsewhere . '/keep.txt');
        $this->assertDirectoryDoesNotExist($work);
    }

    public function test_a_timeout_in_the_middle_of_an_update_puts_the_previous_version_back(): void
    {
        $first = $this->themeZip('1.0.0');
        $second = $this->themeZip('1.1.0', [
            self::SLUG . '/database/migrations/2026_02_01_000000_create_upload_sample_tags.php' => $this->migration('upload_sample_tags'),
            self::SLUG . '/database/migrations/2026_02_02_000000_stall.php' => $this->migrationRunning('set_time_limit(1); while (true) { }'),
        ]);
        $database = $this->scratch . '/probe.sqlite';
        touch($database);

        $process = new Process(
            [(new PhpExecutableFinder())->find() ?: 'php', base_path('tests/fixtures/package_upload_probe.php'), $database, $this->scratch, $first, $second],
            base_path(),
            ['APP_ENV' => 'local', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'CACHE_STORE' => 'array'],
        );
        $process->setTimeout(240);
        $process->run();
        $output = $process->getOutput() . $process->getErrorOutput();

        $this->assertStringContainsString('UPLOADED:installed', $output);
        $this->assertStringContainsString('Maximum execution time', $output);
        $this->assertStringNotContainsString('UPLOADED:updated', $output);

        $this->assertSame('1.0.0', $this->themeVersionOnDisk());
        $this->assertSame([], glob($this->themesRoot . '/.upload-*') ?: []);

        $pdo = new \PDO('sqlite:' . $database);
        $this->assertSame('1.0.0', $pdo->query("select version from extensions where name = 'theme-upload-sample'")->fetchColumn());
        $this->assertSame(PackageUpload::FAILED, $pdo->query('select result from package_uploads order by id desc limit 1')->fetchColumn());
        $this->assertFalse($pdo->query("select name from sqlite_master where type = 'table' and name = 'upload_sample_tags'")->fetchColumn());

        // The lock went with the process.
        $lock = PackageLock::acquire();
        $this->assertNotNull($lock);
        $lock->release();
    }

    // Password, switch and lock --------------------------------------------

    public function test_the_upload_asks_for_the_password_first(): void
    {
        $this->withSession(['auth.password_confirmed_at' => time() - 3600]);

        $this->get(route('admin.extensions.upload'))->assertRedirect(route('password.confirm'));
        $this->upload($this->themeZip('1.0.0'))->assertRedirect(route('password.confirm'));

        $this->assertSame(0, PackageUpload::count());
        $this->assertNothingInstalled();
    }

    public function test_uploads_can_be_switched_off(): void
    {
        config(['extensions.upload.enabled' => false]);

        $this->get(route('admin.extensions.upload'))
            ->assertOk()
            ->assertSee('Package uploads are switched off on this store')
            ->assertDontSee('Upload and install');
        $this->upload($this->themeZip('1.0.0'))->assertNotFound();

        $this->assertSame(0, PackageUpload::count());
        $this->assertNothingInstalled();
    }

    public function test_a_second_upload_waits_for_the_lock_and_is_refused_while_it_is_held(): void
    {
        $held = PackageLock::acquire();
        $this->assertNotNull($held);

        try {
            $this->upload($this->themeZip('1.0.0'));
            $this->assertRefused('Another package is being installed');
        } finally {
            $held->release();
        }

        $this->upload($this->themeZip('1.0.0'));
        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');

        // Released after the upload, whatever its result.
        $after = PackageLock::acquire();
        $this->assertNotNull($after);
        $after->release();
    }

    public function test_the_extension_buttons_take_the_same_lock(): void
    {
        $this->extensionOnDisk('sample-tools');
        $held = PackageLock::acquire();

        try {
            $this->post(route('admin.extensions.install', 'sample-tools'))->assertSessionHas('error', 'Another package is being installed, updated or removed. Try again when it has finished.');
            $this->assertSame('not_installed', app(ExtensionManager::class)->state('sample-tools'));
        } finally {
            $held->release();
        }

        $this->post(route('admin.extensions.install', 'sample-tools'))->assertSessionHas('success');
        $this->assertSame('enabled', app(ExtensionManager::class)->state('sample-tools'));
    }

    public function test_a_folder_that_appears_while_the_package_is_checked_is_never_replaced(): void
    {
        $target = $this->themePath();
        $this->app->bind(PackageUploader::class, fn ($app) => new class($app->make(ExtensionManager::class), $app->make(ThemeService::class), $target) extends PackageUploader
        {
            public function __construct(ExtensionManager $extensions, ThemeService $themes, private string $target)
            {
                parent::__construct($extensions, $themes);
            }

            protected function assertPackageIsComplete(PackageArchive $archive, string $staged, array $bundled): void
            {
                parent::assertPackageIsComplete($archive, $staged, $bundled);

                // A deploy puts the folder there in the meantime.
                File::ensureDirectoryExists($this->target);
                file_put_contents($this->target . '/theme.json', '{"name":"Deployed","version":"1.0.0"}');
            }
        });

        $this->upload($this->themeZip('1.0.0'));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('was not installed by an upload', $this->outcome()['message']);
        $this->assertSame('Deployed', json_decode((string) file_get_contents($target . '/theme.json'), true)['name']);
    }

    // Names, namespaces and migrations of other packages ------------------

    /** @return array<string, array{string}> */
    public static function providersOutOfBounds(): array
    {
        return [
            'application, other case' => ['APP\\Support\\EvilServiceProvider'],
            'framework, other case' => ['ILLUMINATE\\Support\\EvilServiceProvider'],
            'a library' => ['GuzzleHttp\\Evil\\EvilServiceProvider'],
            'a library, other case' => ['psr\\Log\\EvilServiceProvider'],
            'a namespace holding a library' => ['Monolog\\EvilServiceProvider'],
        ];
    }

    #[DataProvider('providersOutOfBounds')]
    public function test_a_provider_in_a_namespace_the_store_runs_on_is_refused(string $provider): void
    {
        $class = substr($provider, (int) strrpos($provider, '\\') + 1);
        $this->upload($this->extensionZip('sample-tools', ['provider' => $provider], ["src/{$class}.php" => '<?php']));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('provider', $this->outcome()['message']);
        $this->assertDirectoryDoesNotExist($this->extensionsRoot . '/sample-tools');
    }

    public function test_a_provider_check_ignores_case(): void
    {
        $this->assertFalse(PackageArchive::validProvider('APP\\Support\\Evil'));
        $this->assertFalse(PackageArchive::validProvider('ILLUMINATE\\Support\\Evil'));
        $this->assertTrue(PackageArchive::validProvider('Apple\\Support\\Provider'));
        $this->assertTrue(PackageArchive::namespacesOverlap('Themes\\Sample\\', 'THEMES\\SAMPLE\\Extensions\\'));
        $this->assertFalse(PackageArchive::namespacesOverlap('Extensions\\Ban\\', 'Extensions\\Banners\\'));
    }

    public function test_a_provider_in_another_packages_namespace_is_refused(): void
    {
        $this->extensionOnDisk('demo-shipped', ['provider' => 'Demo\\Shipped\\ShippedServiceProvider']);

        foreach (['Demo\\Shipped\\TakeoverServiceProvider', 'DEMO\\SHIPPED\\Inner\\TakeoverServiceProvider', 'Demo\\TakeoverServiceProvider'] as $provider) {
            $class = substr($provider, (int) strrpos($provider, '\\') + 1);
            $this->upload($this->extensionZip('sample-tools', ['provider' => $provider], ["src/{$class}.php" => '<?php']));

            $this->assertFalse($this->outcome()['ok'], $provider);
            $this->assertStringContainsString('Shipped demo-shipped already uses', $this->outcome()['message']);
        }

        $this->assertDirectoryDoesNotExist($this->extensionsRoot . '/sample-tools');
    }

    public function test_a_migration_named_like_a_core_migration_is_refused(): void
    {
        $this->upload($this->extensionZip('sample-tools', [], [
            'database/migrations/2026_10_26_100000_create_package_uploads.php' => $this->migration('sample_tools_items'),
        ]));

        $this->assertRefused('has the same name as one of the store itself');
        $this->assertDirectoryDoesNotExist($this->extensionsRoot . '/sample-tools');
    }

    public function test_a_migration_named_like_another_packages_migration_is_refused(): void
    {
        $this->extensionOnDisk('demo-shipped', [], ['database/migrations/2026_01_01_000000_create_demo_items.php' => $this->migration('demo_items')]);

        $this->upload($this->extensionZip('sample-tools', [], [
            'database/migrations/2026_01_01_000000_create_demo_items.php' => $this->migration('sample_tools_items'),
        ]));

        $this->assertRefused('has the same name as one of Shipped demo-shipped');
    }

    public function test_a_migration_that_already_ran_from_elsewhere_is_refused(): void
    {
        DB::table('migrations')->insert(['migration' => '2026_01_01_000000_create_sample_tools_items', 'batch' => 1]);

        $this->upload($this->extensionZip('sample-tools', [], [
            'database/migrations/2026_01_01_000000_create_sample_tools_items.php' => $this->migration('sample_tools_items'),
        ]));

        $this->assertRefused('has already run on this store from somewhere else');
    }

    public function test_an_update_may_bring_the_migrations_it_ran_before(): void
    {
        $this->upload($this->themeZip('1.0.0'));
        $this->upload($this->themeZip('1.1.0'));

        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
    }

    public function test_two_migrations_of_one_name_in_a_package_are_refused(): void
    {
        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/extensions/upload-sample-notes/extension.json' => json_encode(['name' => 'upload-sample-notes', 'title' => 'Notes', 'version' => '1.0.0']),
            self::SLUG . '/extensions/upload-sample-notes/database/migrations/2026_01_01_000000_create_upload_sample_notes.php' => $this->migration('upload_sample_notes_again'),
        ]));

        $this->assertRefused('brings two migrations called 2026_01_01_000000_create_upload_sample_notes');
    }

    // Extensions a theme brings ---------------------------------------------

    private function themeWithNotes(string $themeVersion, string $notesVersion, array $migrations = []): string
    {
        $extra = [
            self::SLUG . '/extensions/upload-sample-notes/extension.json' => json_encode(['name' => 'upload-sample-notes', 'title' => 'Sample notes', 'version' => $notesVersion]),
        ];
        foreach ($migrations as $file => $table) {
            $extra[self::SLUG . "/extensions/upload-sample-notes/database/migrations/{$file}.php"] = $this->migration($table);
        }

        return $this->themeZip($themeVersion, $extra);
    }

    public function test_updating_a_theme_updates_the_extensions_it_brings_that_are_installed(): void
    {
        $this->upload($this->themeWithNotes('1.0.0', '1.0.0', ['2026_01_05_000000_create_sample_note_pins' => 'sample_note_pins']));
        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        app(ExtensionManager::class)->rescan();
        app(ExtensionManager::class)->install('upload-sample-notes');
        $this->assertTrue(Schema::hasTable('sample_note_pins'));

        $this->upload($this->themeWithNotes('1.1.0', '1.1.0', [
            '2026_01_05_000000_create_sample_note_pins' => 'sample_note_pins',
            '2026_02_05_000000_create_sample_note_tags' => 'sample_note_tags',
        ]));

        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        $this->assertSame('1.1.0', Extension::where('name', 'upload-sample-notes')->value('version'));
        $this->assertTrue(Schema::hasTable('sample_note_tags'));
        $this->assertSame('enabled', app(ExtensionManager::class)->state('upload-sample-notes'));
    }

    public function test_a_theme_that_would_downgrade_an_extension_it_brings_is_refused(): void
    {
        $this->upload($this->themeWithNotes('1.0.0', '1.2.0'));
        app(ExtensionManager::class)->rescan();
        app(ExtensionManager::class)->install('upload-sample-notes');

        $this->upload($this->themeWithNotes('1.1.0', '1.1.0'));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('older than the 1.2.0 installed', $this->outcome()['message']);
        $this->assertSame('1.0.0', $this->themeVersionOnDisk());
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function badBundledExtensions(): array
    {
        return [
            'a name core keeps' => [['name' => 'upload'], 'a name the store keeps'],
            'a name that is not a slug' => [['name' => 'Bad_Name'], 'needs a name'],
            'a provider in the application' => [['name' => 'upload-sample-notes', 'provider' => 'App\\Notes\\NotesServiceProvider'], 'provider class'],
            'a provider in a library' => [['name' => 'upload-sample-notes', 'provider' => 'Carbon\\Notes\\NotesServiceProvider'], 'library the store runs on'],
            'no version' => [['name' => 'upload-sample-notes', 'version' => null], 'needs a version'],
            'a version too long' => [['name' => 'upload-sample-notes', 'version' => '1.0.0-' . str_repeat('c', 40)], 'needs a version'],
        ];
    }

    #[DataProvider('badBundledExtensions')]
    public function test_an_extension_a_theme_brings_is_checked_like_any_other(array $manifest, string $message): void
    {
        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/extensions/notes/extension.json' => json_encode(array_filter($manifest + ['title' => 'Notes', 'version' => '1.0.0'], fn ($value) => $value !== null)),
        ]));

        $this->assertRefused($message);
    }

    public function test_an_extension_a_theme_brings_may_not_take_another_packages_name(): void
    {
        $this->extensionOnDisk('demo-shipped');

        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/extensions/demo-shipped/extension.json' => json_encode(['name' => 'demo-shipped', 'title' => 'Mine', 'version' => '1.0.0']),
        ]));

        $this->assertRefused('another package on the server already uses that name');
        $this->assertSame('Shipped demo-shipped', json_decode((string) file_get_contents($this->extensionsRoot . '/demo-shipped/extension.json'), true)['title']);
    }

    // The archive ---------------------------------------------------------

    public function test_an_entry_that_inflates_past_its_declared_size_is_stopped(): void
    {
        $zip = $this->themeZip('1.0.0', [self::SLUG . '/demo/padding.bin' => str_repeat('0', 300000)]);

        // Lie in both headers: the entry says it unpacks to 1,000 bytes.
        $bytes = (string) file_get_contents($zip);
        $name = self::SLUG . '/demo/padding.bin';
        foreach (["PK\x01\x02" => 24, "PK\x03\x04" => 22] as $signature => $offset) {
            $at = 0;
            while (($at = strpos($bytes, $signature, $at)) !== false) {
                $nameAt = $signature === "PK\x01\x02" ? $at + 46 : $at + 30;
                if (substr($bytes, $nameAt, strlen($name)) === $name) {
                    $bytes = substr_replace($bytes, pack('V', 1000), $at + $offset, 4);
                }
                $at++;
            }
        }
        file_put_contents($zip, $bytes);

        $this->upload($zip);

        $this->assertRefused('unpacks to more than the archive says it holds');
    }

    public function test_the_total_unpacked_size_is_limited(): void
    {
        config(['extensions.upload.max_unpacked_mb' => 1, 'extensions.upload.max_file_mb' => 1]);

        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/demo/a.bin' => str_repeat('a', 400000),
            self::SLUG . '/demo/b.bin' => str_repeat('b', 400000),
            self::SLUG . '/demo/c.bin' => str_repeat('c', 400000),
        ]));

        $this->assertRefused('unpacks to more than 1 MB');
    }

    /** @return array<string, array{string, string}> */
    public static function unsafeNames(): array
    {
        return [
            'a backslash' => [self::SLUG . '\\views\\evil.php', 'backslashes'],
            'a drive letter' => ['C:/' . self::SLUG . '/evil.php', 'absolute path'],
            'a control character' => [self::SLUG . "/views/a\x01b.php", 'unreadable name'],
            'a colon' => [self::SLUG . '/views/a.php:stream', 'not every system can store'],
            'a trailing dot' => [self::SLUG . '/views/evil.php.', 'not every system can store'],
            'a trailing space' => [self::SLUG . '/views/evil.php ', 'not every system can store'],
            'a device name' => [self::SLUG . '/views/CON', 'not every system can store'],
            'a device name with an extension' => [self::SLUG . '/views/nul.txt', 'not every system can store'],
            'a dot segment' => [self::SLUG . '/./views/a.php', 'not plain'],
            'an empty segment' => [self::SLUG . '//views/a.php', 'not plain'],
        ];
    }

    #[DataProvider('unsafeNames')]
    public function test_an_unsafe_entry_name_is_refused(string $name, string $message): void
    {
        $this->upload($this->themeZip('1.0.0', [$name => '<?php echo 1;']));

        $this->assertRefused($message);
    }

    public function test_a_nul_in_a_name_cannot_cut_a_path_past_the_checks(): void
    {
        $zip = $this->themeZip('1.0.0', [self::SLUG . '/views/aQ/../../../../escaped.php' => '<?php echo 1;']);
        $this->patchZip($zip, 'aQ/../../', "a\0/../../");

        $this->upload($zip);

        $this->assertFalse($this->outcome()['ok']);
        $this->assertFileDoesNotExist($this->scratch . '/escaped.php');
        $this->assertNothingInstalled();
    }

    public function test_an_entry_listed_twice_is_refused(): void
    {
        $zip = $this->themeZip('1.0.0', [self::SLUG . '/views/dupa.blade.php' => 'a', self::SLUG . '/views/dupb.blade.php' => 'b']);
        $this->patchZip($zip, 'dupb.blade.php', 'dupa.blade.php');

        $this->upload($zip);

        $this->assertRefused('more than once');
    }

    public function test_names_that_differ_only_in_case_are_refused(): void
    {
        $this->upload($this->themeZip('1.0.0', [self::SLUG . '/demo/Readme.md' => 'a', self::SLUG . '/demo/README.md' => 'b']));

        $this->assertRefused('more than once');
    }

    public function test_a_file_and_a_folder_of_one_name_are_refused(): void
    {
        $zip = $this->themeZip('1.0.0', [self::SLUG . '/demo/item' => 'a']);
        $archive = new ZipArchive();
        $archive->open($zip);
        $archive->addEmptyDir(self::SLUG . '/demo/item');
        $archive->close();

        $this->upload($zip);

        $this->assertRefused('more than once');
    }

    public function test_an_encrypted_entry_is_refused(): void
    {
        $zip = $this->themeZip('1.0.0', [self::SLUG . '/demo/secret.txt' => 'hidden']);
        $archive = new ZipArchive();
        $archive->open($zip);
        if (! $archive->setEncryptionName(self::SLUG . '/demo/secret.txt', ZipArchive::EM_AES_256, 'demo-password')) {
            $this->markTestSkipped('This build of libzip cannot encrypt.');
        }
        $archive->close();

        $this->upload($zip);

        $this->assertRefused('password protected');
    }

    // build/ ----------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function dangerousBuildFiles(): array
    {
        return [
            '.htaccess' => ['.htaccess'],
            '.user.ini' => ['.user.ini'],
            'a .phtml' => ['assets/page.phtml'],
            'a .phar' => ['assets/tool.phar'],
            'a double extension' => ['assets/x.php.css'],
            'a double extension, other case' => ['assets/x.PHTML.js'],
            'an SVG' => ['assets/icon.svg'],
            'an HTML page' => ['index.html'],
            'a hidden folder' => ['.vite/x.js'],
        ];
    }

    #[DataProvider('dangerousBuildFiles')]
    public function test_a_build_holding_a_runnable_file_is_refused(string $file): void
    {
        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/build/manifest.json' => '{}',
            self::SLUG . '/build/' . $file => '<?php echo 1;',
        ]));

        $this->assertRefused('build/ folder holds files the store will not publish');
        $this->assertDirectoryDoesNotExist(PackageAssets::publicPath('theme-' . self::SLUG));
    }

    public function test_the_build_check_reads_every_part_of_a_name(): void
    {
        $this->assertTrue(PackageAssets::servable('assets/theme-BcRnCWpz.js'));
        $this->assertTrue(PackageAssets::servable('assets/panel-C_--7cuN.css'));
        $this->assertTrue(PackageAssets::servable('fonts/inter.woff2'));
        $this->assertFalse(PackageAssets::servable('assets/x.php.css'));
        $this->assertFalse(PackageAssets::servable('assets/x.phtml'));
        $this->assertFalse(PackageAssets::servable('assets/icon.svg'));
        $this->assertFalse(PackageAssets::servable('.htaccess'));
        $this->assertFalse(PackageAssets::servable('.vite/manifest.json'));
        $this->assertFalse(PackageAssets::servable('assets/notes.txt'));
    }

    public function test_a_package_build_cannot_take_over_another_packages_entries(): void
    {
        $vite = new PackageVite();
        $merge = new \ReflectionMethod($vite, 'merge');

        $merged = $merge->invoke($vite, ['resources/css/app.css' => ['file' => 'assets/app.css']], [
            'theme-sample-clone' => [
                'resources/themes/sample/css/theme.css' => ['file' => 'assets/stolen.css', 'isEntry' => true],
                'resources/css/app.css' => ['file' => 'assets/stolen-app.css', 'isEntry' => true],
                'resources/themes/sample-clone/css/theme.css' => ['file' => 'assets/own.css', 'isEntry' => true, 'imports' => ['resources/themes/sample/js/x.js', '_shared.js']],
                '_shared.js' => ['file' => 'assets/shared.js'],
            ],
            'sample-tools' => [
                'extensions/other-tools/js/x.js' => ['file' => 'assets/stolen.js', 'isEntry' => true],
                'extensions/sample-tools/js/tools.js' => ['file' => 'assets/tools.js', 'isEntry' => true],
            ],
        ], 'build');

        $this->assertSame('assets/app.css', $merged['resources/css/app.css']['file']);
        $this->assertArrayNotHasKey('resources/themes/sample/css/theme.css', $merged);
        $this->assertArrayNotHasKey('extensions/other-tools/js/x.js', $merged);
        $this->assertSame('../packages/theme-sample-clone/assets/own.css', $merged['resources/themes/sample-clone/css/theme.css']['file']);
        $this->assertSame(['_theme-sample-clone/shared.js'], $merged['resources/themes/sample-clone/css/theme.css']['imports']);
        $this->assertSame('../packages/sample-tools/assets/tools.js', $merged['extensions/sample-tools/js/tools.js']['file']);
    }

    // Nothing runs before the swap -------------------------------------------

    public function test_a_refused_package_never_ran_any_of_its_code(): void
    {
        $marker = $this->scratch . '/code-ran.txt';
        $code = "file_put_contents('{$marker}', 'ran');";

        // Refused at the last check, after unpacking: the build holds an
        // .htaccess. Its migration, provider and views would each leave
        // the marker if anything loaded them.
        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/database/migrations/2026_01_03_000000_mark.php' => $this->migrationRunning($code),
            self::SLUG . '/src/MarkServiceProvider.php' => "<?php {$code}",
            self::SLUG . '/views/marker.blade.php' => "@php {$code} @endphp",
            self::SLUG . '/build/manifest.json' => '{}',
            self::SLUG . '/build/.htaccess' => 'php_flag engine on',
        ], self::SLUG, fn (array $meta) => ['provider' => 'Demo\\Marker\\MarkServiceProvider'] + $meta));

        $this->assertRefused('build/ folder holds files');
        $this->assertFileDoesNotExist($marker);
        $this->assertFalse(class_exists('Demo\\Marker\\MarkServiceProvider', false));
    }

    // The log ---------------------------------------------------------------

    public function test_line_breaks_in_a_manifest_never_reach_the_log(): void
    {
        $log = $this->scratch . '/forging.log';
        config([
            'logging.channels.package-forging' => ['driver' => 'single', 'path' => $log, 'level' => 'debug'],
            'logging.default' => 'package-forging',
        ]);
        $forged = "Upload Sample\n[2026-01-01 00:00:00] production.INFO: Package upload: forged line\r\x1b[0m";

        $this->upload($this->themeZip('1.0.0', [], self::SLUG, fn (array $meta) => ['name' => $forged] + $meta));
        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        $this->upload($this->zip(['sample-tools/extension.json' => json_encode(['name' => "other\n[2026-01-01 00:00:00] production.INFO: forged", 'version' => '1.0.0'])]));
        $this->assertFalse($this->outcome()['ok']);

        $written = (string) file_get_contents($log);
        $this->assertStringContainsString('forged line', $written);
        $this->assertDoesNotMatchRegularExpression('/^\[2026-01-01 00:00:00\]/m', $written);
        $this->assertStringNotContainsString("\x1b", $written);
        foreach (PackageUpload::pluck('message') as $message) {
            $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', (string) $message);
        }
    }

    public function test_plain_text_drops_control_characters_and_cuts_long_text(): void
    {
        $this->assertSame('a b c', PackageArchive::plain("a\nb\x00c"));
        $this->assertSame('a b', PackageArchive::plain("a\u{2028}b"));
        $this->assertSame(10, mb_strlen(PackageArchive::plain(str_repeat('é', 50), 10)));
        $this->assertTrue(mb_check_encoding(PackageArchive::plain("\xff\xfeok"), 'UTF-8'));
    }
}
