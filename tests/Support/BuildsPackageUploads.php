<?php

namespace Tests\Support;

use App\Models\PackageUpload;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Package zips for tests of Extensions > Upload, and a scratch store for
 * them to land in.
 */
trait BuildsPackageUploads
{

    public const SLUG = 'upload-sample';

    protected string $scratch;

    protected string $extensionsRoot;

    protected string $themesRoot;

    protected string $publicRoot;

    /**
     * Every folder a test writes to is its own: themes, extensions, the
     * published builds and the lock live in a scratch folder named for
     * this run, so tests never touch the store's real folders or another
     * run's uploads. The default theme is linked in, because a theme zip
     * is made from it and the store needs one to draw with.
     */
    protected function setUpPackageScratch(): void
    {

        $this->scratch = storage_path('framework/testing/package-upload-' . bin2hex(random_bytes(6)));
        $this->extensionsRoot = $this->scratch . '/extensions';
        $this->themesRoot = $this->scratch . '/themes';
        $this->publicRoot = $this->scratch . '/public-packages';
        File::ensureDirectoryExists($this->extensionsRoot);
        File::ensureDirectoryExists($this->themesRoot);
        symlink(resource_path('themes/' . ThemeService::THEME), $this->themesRoot . '/' . ThemeService::THEME);

        config([
            'extensions.path' => $this->extensionsRoot,
            'extensions.themes_path' => $this->themesRoot,
            'extensions.public_path' => $this->publicRoot,
            'extensions.upload.lock_file' => $this->scratch . '/package-changes.lock',
            'extensions.upload.lock_wait_seconds' => 0,
        ]);

        $this->beforeApplicationDestroyed(function () {
            File::deleteDirectory($this->scratch);
        });

        app()->forgetInstance(ExtensionManager::class);
        $this->actingAs(User::factory()->create(['name' => 'Maria Santos']));
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    protected function themePath(string $path = ''): string
    {
        return $this->themesRoot . '/' . self::SLUG . ($path === '' ? '' : '/' . $path);
    }

    /**
     * A zip of the default theme under another folder name, at $version,
     * with a migration of its own. Extra entries are added as given.
     *
     * @param  array<string, string>  $extra
     */
    protected function themeZip(string $version = '1.0.0', array $extra = [], string $folder = self::SLUG, ?callable $manifest = null): string
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

    protected function migration(string $table): string
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
    protected function zip(array $files, array $links = []): string
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

    protected function upload(string $zip, string $name = 'package.zip')
    {
        return $this->post(route('admin.extensions.upload.store'), [
            'package' => new UploadedFile($zip, $name, 'application/zip', null, true),
        ]);
    }

    protected function outcome(): array
    {
        return (array) session('package_upload');
    }

    protected function assertRefused(string $contains): void
    {
        $result = $this->outcome();
        $this->assertFalse($result['ok'] ?? true, 'The upload was expected to be refused.');
        $this->assertStringContainsString($contains, $result['message']);
        $this->assertSame(PackageUpload::REFUSED, PackageUpload::latest('id')->value('result'));
        $this->assertDirectoryDoesNotExist($this->themePath());
        $this->assertSame([], glob($this->themesRoot . '/.upload-*') ?: []);
    }
}
