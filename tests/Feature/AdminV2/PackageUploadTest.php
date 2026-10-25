<?php

namespace Tests\Feature\AdminV2;

use App\Models\Extension;
use App\Models\PackageUpload;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\PackageAssets;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * Extensions > Upload: a theme or extension installed from a .zip, and
 * updated by uploading a newer one. Everything an archive could do to the
 * server beyond its own folder is refused before a file is written.
 */
class PackageUploadTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'upload-sample';

    private string $scratch;

    private string $extensionsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = storage_path('framework/testing/package-upload');
        $this->extensionsRoot = $this->scratch . '/extensions';
        File::deleteDirectory($this->scratch);
        File::ensureDirectoryExists($this->extensionsRoot);
        config(['extensions.path' => $this->extensionsRoot]);
        $this->forgetTheme();

        $this->beforeApplicationDestroyed(function () {
            $this->forgetTheme();
            File::deleteDirectory($this->scratch);
        });

        app()->forgetInstance(ExtensionManager::class);
        $this->actingAs(User::factory()->create(['name' => 'Maria Santos']));
    }

    private function forgetTheme(): void
    {
        $theme = resource_path('themes/' . self::SLUG);
        if (is_link($theme)) {
            unlink($theme);
        }
        File::deleteDirectory($theme);
        foreach (glob(resource_path('themes/.upload-*'), GLOB_ONLYDIR) ?: [] as $left) {
            File::deleteDirectory($left);
        }
        PackageAssets::remove(ThemeService::registryName(self::SLUG));
    }

    /**
     * A zip of the default theme under another folder name, at $version,
     * with a migration of its own. Extra entries are added as given.
     *
     * @param  array<string, string>  $extra
     */
    private function themeZip(string $version = '1.0.0', array $extra = [], string $folder = self::SLUG, ?callable $manifest = null): string
    {
        $default = resource_path('themes/' . ThemeService::THEME);
        $files = [];

        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($default, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS));
        foreach ($items as $item) {
            if ($item->isFile()) {
                $files[$folder . '/' . substr($item->getPathname(), strlen($default) + 1)] = (string) file_get_contents($item->getPathname());
            }
        }

        $meta = json_decode($files["{$folder}/theme.json"], true);
        $meta['name'] = 'Upload Sample';
        $meta['version'] = $version;
        if ($manifest) {
            $meta = $manifest($meta);
        }
        $files["{$folder}/theme.json"] = json_encode($meta, JSON_PRETTY_PRINT);
        $files["{$folder}/database/migrations/2026_01_01_000000_create_upload_sample_notes.php"] = $this->migration('upload_sample_notes');

        return $this->zip($files + $extra);
    }

    private function migration(string $table): string
    {
        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table) {
                    \$table->id();
                    \$table->string('body')->nullable();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP;
    }

    /**
     * @param  array<string, string>  $files  entry name => contents
     * @param  array<string, string>  $links  entry name => link target
     */
    private function zip(array $files, array $links = []): string
    {
        $path = $this->scratch . '/' . bin2hex(random_bytes(6)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        foreach ($links as $name => $target) {
            $zip->addFromString($name, $target);
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0120777 << 16));
        }
        $zip->close();

        return $path;
    }

    private function upload(string $zip, string $name = 'package.zip')
    {
        return $this->post(route('admin.extensions.upload.store'), [
            'package' => new UploadedFile($zip, $name, 'application/zip', null, true),
        ]);
    }

    private function outcome(): array
    {
        return (array) session('package_upload');
    }

    private function assertRefused(string $contains): void
    {
        $result = $this->outcome();
        $this->assertFalse($result['ok'] ?? true, 'The upload was expected to be refused.');
        $this->assertStringContainsString($contains, $result['message']);
        $this->assertSame(PackageUpload::REFUSED, PackageUpload::latest('id')->value('result'));
        $this->assertDirectoryDoesNotExist(resource_path('themes/' . self::SLUG));
        $this->assertSame([], glob(resource_path('themes/.upload-*')) ?: []);
    }

    public function test_the_screen_opens_for_a_super_admin(): void
    {
        $this->get(route('admin.extensions.upload'))
            ->assertOk()
            ->assertSee('Upload a theme or extension')
            ->assertSee('No uploads yet');
    }

    public function test_a_good_theme_zip_installs_and_runs_its_migrations(): void
    {
        $this->upload($this->themeZip('1.0.0'), 'upload-sample-1.0.0.zip')
            ->assertRedirect(route('admin.extensions.upload'));

        $result = $this->outcome();
        $this->assertTrue($result['ok'], $result['message'] ?? '');
        $this->assertSame('1.0.0', $result['version']);
        $this->assertFalse($result['updated']);

        $this->assertFileExists(resource_path('themes/' . self::SLUG . '/theme.json'));
        $this->assertSame('1.0.0', Extension::where('name', 'theme-' . self::SLUG)->value('version'));
        $this->assertTrue(Schema::hasTable('upload_sample_notes'));
        $this->assertTrue(app(ThemeService::class)->getAvailableThemes()[self::SLUG]['installed']);

        $record = PackageUpload::sole();
        $this->assertSame(PackageUpload::INSTALLED, $record->result);
        $this->assertSame('theme', $record->kind);
        $this->assertSame(auth()->id(), $record->user_id);
        $this->assertSame('upload-sample-1.0.0.zip', $record->filename);
        $this->assertSame(64, strlen($record->sha256));

        $this->get(route('admin.extensions.upload'))
            ->assertSee('Installed version 1.0.0')
            ->assertSee('upload-sample-1.0.0.zip')
            ->assertSee('Maria Santos');
    }

    public function test_an_update_zip_bumps_the_version_and_keeps_what_the_store_saved(): void
    {
        $this->upload($this->themeZip('1.0.0'));
        DB::table('upload_sample_notes')->insert(['body' => 'Kept']);
        Setting::create(['group' => 'theme_settings', 'key' => self::SLUG . '_header_layout', 'value' => 'centered']);

        $update = $this->themeZip('1.1.0', [
            self::SLUG . '/database/migrations/2026_02_01_000000_create_upload_sample_tags.php' => $this->migration('upload_sample_tags'),
            self::SLUG . '/views/partials/added-in-update.blade.php' => '<div></div>',
        ]);
        $this->upload($update);

        $result = $this->outcome();
        $this->assertTrue($result['ok'], $result['message'] ?? '');
        $this->assertTrue($result['updated']);
        $this->assertSame('1.0.0', $result['previous_version']);
        $this->assertStringContainsString('updated from 1.0.0 to 1.1.0', $result['message']);

        $this->assertSame('1.1.0', Extension::where('name', 'theme-' . self::SLUG)->value('version'));
        $this->assertTrue(Schema::hasTable('upload_sample_tags'));
        $this->assertSame('Kept', DB::table('upload_sample_notes')->value('body'));
        $this->assertSame('centered', Setting::where('group', 'theme_settings')->where('key', self::SLUG . '_header_layout')->value('value'));
        $this->assertFileExists(resource_path('themes/' . self::SLUG . '/views/partials/added-in-update.blade.php'));
        $this->assertSame(PackageUpload::UPDATED, PackageUpload::latest('id')->value('result'));

        $this->get(route('admin.extensions.upload'))->assertSee('Updated from version 1.0.0 to 1.1.0');
    }

    public function test_the_same_version_can_be_uploaded_again(): void
    {
        $zip = $this->themeZip('1.0.0');
        $this->upload($zip);
        $this->upload($zip);

        $this->assertTrue($this->outcome()['ok']);
        $this->assertStringContainsString('installed again', $this->outcome()['message']);
    }

    public function test_an_older_version_is_refused(): void
    {
        $this->upload($this->themeZip('1.2.0'));
        $this->upload($this->themeZip('1.1.0'));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('older than the 1.2.0', $this->outcome()['message']);
        $this->assertSame('1.2.0', json_decode(file_get_contents(resource_path('themes/' . self::SLUG . '/theme.json')), true)['version']);
    }

    public function test_a_zip_slip_path_is_refused(): void
    {
        $this->upload($this->themeZip('1.0.0', [self::SLUG . '/../../escaped.php' => '<?php echo 1;']));

        $this->assertRefused('leaves its folder');
        $this->assertFileDoesNotExist(resource_path('escaped.php'));
        $this->assertFileDoesNotExist(base_path('escaped.php'));
    }

    public function test_an_absolute_path_is_refused(): void
    {
        $this->upload($this->zip([
            '/tmp/upload-sample-absolute.txt' => 'x',
            self::SLUG . '/theme.json' => '{"name":"Sample","version":"1.0.0"}',
        ]));

        $this->assertRefused('absolute path');
        $this->assertFileDoesNotExist('/tmp/upload-sample-absolute.txt');
    }

    public function test_a_link_is_refused(): void
    {
        $zip = $this->themeZip('1.0.0');
        $archive = new ZipArchive();
        $archive->open($zip);
        $archive->addFromString(self::SLUG . '/views/env.blade.php', '../../../.env');
        $archive->setExternalAttributesName(self::SLUG . '/views/env.blade.php', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $archive->close();

        $this->upload($zip);

        $this->assertRefused('holds a link');
    }

    public function test_an_oversized_file_is_refused(): void
    {
        config(['extensions.upload.max_file_mb' => 1]);

        $this->upload($this->themeZip('1.0.0', [self::SLUG . '/demo/big.bin' => str_repeat('0', 1048577)]));

        $this->assertRefused('one file may be at most 1 MB');
    }

    public function test_too_many_files_are_refused(): void
    {
        config(['extensions.upload.max_files' => 10]);

        $this->upload($this->themeZip('1.0.0'));

        $this->assertRefused('at most 10');
    }

    public function test_an_upload_over_the_size_limit_is_refused_before_it_is_opened(): void
    {
        config(['extensions.upload.max_size_mb' => 1]);
        $zip = $this->scratch . '/big.zip';
        $archive = new ZipArchive();
        $archive->open($zip, ZipArchive::CREATE);
        $archive->addFromString(self::SLUG . '/theme.json', '{}');
        $archive->addFromString(self::SLUG . '/noise.bin', random_bytes(1200000));
        $archive->setCompressionName(self::SLUG . '/noise.bin', ZipArchive::CM_STORE);
        $archive->close();

        $this->upload($zip)->assertSessionHasErrors('package');
        $this->assertSame(0, PackageUpload::count());
    }

    public function test_a_file_that_is_not_a_zip_is_refused(): void
    {
        $file = $this->scratch . '/notes.zip';
        file_put_contents($file, 'plain text');

        $this->upload($file)->assertSessionHasErrors('package');
        $this->assertDirectoryDoesNotExist(resource_path('themes/' . self::SLUG));
    }

    public function test_an_archive_without_a_manifest_is_refused(): void
    {
        $this->upload($this->zip([self::SLUG . '/views/home.blade.php' => '<div></div>']));

        $this->assertRefused('has no theme.json or extension.json');
    }

    public function test_an_archive_with_files_outside_one_folder_is_refused(): void
    {
        $this->upload($this->zip([
            self::SLUG . '/extension.json' => json_encode(['name' => self::SLUG, 'version' => '1.0.0']),
            'other/readme.txt' => 'x',
        ]));

        $this->assertRefused('more than one folder');
    }

    public function test_a_manifest_whose_name_does_not_match_its_folder_is_refused(): void
    {
        $this->upload($this->zip([
            'sample-tools/extension.json' => json_encode(['name' => 'other-tools', 'title' => 'Sample tools', 'version' => '1.0.0']),
        ]));

        $this->assertRefused('but its folder is "sample-tools"');
        $this->assertDirectoryDoesNotExist($this->extensionsRoot . '/sample-tools');
    }

    public function test_a_folder_name_that_is_not_a_slug_is_refused(): void
    {
        $this->upload($this->zip(['Sample_Theme/theme.json' => '{"name":"Sample","version":"1.0.0"}']));

        $this->assertRefused('is not a valid name');
    }

    public function test_a_theme_that_breaks_the_contract_is_refused_and_leaves_nothing(): void
    {
        $this->upload($this->zip([self::SLUG . '/theme.json' => json_encode(['name' => 'Sample', 'version' => '1.0.0'])]));

        $this->assertRefused('does not keep the theme contract');
        $this->assertNull(Extension::where('name', 'theme-' . self::SLUG)->first());
    }

    public function test_the_default_theme_is_never_overwritten(): void
    {
        $before = md5_file(resource_path('themes/venta/theme.json'));

        $this->upload($this->themeZip('9.0.0', [], ThemeService::THEME));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('default theme', $this->outcome()['message']);
        $this->assertSame($before, md5_file(resource_path('themes/venta/theme.json')));
    }

    public function test_a_folder_the_store_did_not_get_from_an_upload_is_never_overwritten(): void
    {
        File::ensureDirectoryExists($this->extensionsRoot . '/sample-tools');
        file_put_contents($this->extensionsRoot . '/sample-tools/extension.json', json_encode(['name' => 'sample-tools', 'title' => 'Shipped', 'version' => '1.0.0']));

        $this->upload($this->zip([
            'sample-tools/extension.json' => json_encode(['name' => 'sample-tools', 'title' => 'Replacement', 'version' => '2.0.0']),
        ]));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('was not installed by an upload', $this->outcome()['message']);
        $this->assertSame('Shipped', json_decode(file_get_contents($this->extensionsRoot . '/sample-tools/extension.json'), true)['title']);
    }

    public function test_a_linked_theme_folder_is_never_overwritten(): void
    {
        $elsewhere = $this->scratch . '/linked-theme';
        File::ensureDirectoryExists($elsewhere);
        file_put_contents($elsewhere . '/theme.json', '{"name":"Linked","version":"1.0.0"}');
        symlink($elsewhere, resource_path('themes/' . self::SLUG));

        $this->upload($this->themeZip('2.0.0'));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('is a link', $this->outcome()['message']);
        $this->assertSame('Linked', json_decode(file_get_contents($elsewhere . '/theme.json'), true)['name']);
    }

    public function test_an_extension_zip_installs_into_the_extensions_folder(): void
    {
        $this->upload($this->zip([
            'sample-tools/extension.json' => json_encode(['name' => 'sample-tools', 'title' => 'Sample tools', 'version' => '1.0.0', 'type' => 'feature']),
            'sample-tools/database/migrations/2026_01_01_000000_create_sample_tools_items.php' => $this->migration('sample_tools_items'),
        ]));

        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        $this->assertFileExists($this->extensionsRoot . '/sample-tools/extension.json');
        $this->assertTrue(Schema::hasTable('sample_tools_items'));
        $this->assertSame('enabled', app(ExtensionManager::class)->state('sample-tools'));
    }

    public function test_an_extension_may_not_take_a_name_the_store_uses(): void
    {
        $this->upload($this->zip([
            'upload/extension.json' => json_encode(['name' => 'upload', 'title' => 'Upload', 'version' => '1.0.0']),
        ]));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('keeps for its own pages', $this->outcome()['message']);
    }

    public function test_a_provider_in_the_application_namespace_is_refused(): void
    {
        $this->upload($this->zip([
            'sample-tools/extension.json' => json_encode(['name' => 'sample-tools', 'title' => 'Sample tools', 'version' => '1.0.0', 'provider' => 'App\\Providers\\SampleProvider']),
        ]));

        $this->assertFalse($this->outcome()['ok']);
        $this->assertStringContainsString('provider class', $this->outcome()['message']);
    }

    public function test_a_package_build_is_published_and_served(): void
    {
        $this->upload($this->themeZip('1.0.0', [
            self::SLUG . '/build/manifest.json' => json_encode([
                'resources/themes/upload-sample/css/extra.css' => ['file' => 'assets/extra-abc123.css', 'src' => 'resources/themes/upload-sample/css/extra.css', 'isEntry' => true],
            ]),
            self::SLUG . '/build/assets/extra-abc123.css' => 'body{}',
            self::SLUG . '/build/assets/run.php' => '<?php echo 1;',
        ]));

        $this->assertTrue($this->outcome()['ok'], $this->outcome()['message'] ?? '');
        $published = PackageAssets::publicPath('theme-' . self::SLUG);
        $this->assertFileExists($published . '/assets/extra-abc123.css');
        $this->assertFileDoesNotExist($published . '/assets/run.php');

        if (is_file(public_path('build/manifest.json'))) {
            $html = (string) app(\Illuminate\Foundation\Vite::class)('resources/themes/upload-sample/css/extra.css');
            $this->assertStringContainsString('/packages/theme-upload-sample/assets/extra-abc123.css', $html);
            $this->assertStringNotContainsString('..', $html);
        }

        app(ExtensionManager::class)->uninstall('theme-' . self::SLUG);
        $this->assertDirectoryDoesNotExist($published);
    }

    public function test_only_a_super_admin_reaches_the_upload(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'Everything', 'slug' => 'everything']);
        $role->permissions()->sync(Permission::pluck('id'));
        $this->actingAs(User::factory()->role('everything')->create());

        $this->get(route('admin.extensions.upload'))->assertForbidden();
        $this->upload($this->themeZip('1.0.0'))->assertForbidden();

        $this->assertDirectoryDoesNotExist(resource_path('themes/' . self::SLUG));
        $this->assertSame(0, PackageUpload::count());
        $this->get(route('admin.extensions.index'))->assertOk()->assertDontSee(route('admin.extensions.upload'));
    }

    public function test_uploads_are_rate_limited(): void
    {
        config(['extensions.upload.per_minute' => 2]);
        $notZip = $this->scratch . '/notes.zip';
        file_put_contents($notZip, 'plain text');

        $this->upload($notZip);
        $this->upload($notZip);
        $this->upload($notZip)->assertStatus(429);
    }
}
