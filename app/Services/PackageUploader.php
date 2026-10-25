<?php

namespace App\Services;

use App\Exceptions\PackageRefused;
use App\Models\Extension;
use App\Models\PackageUpload;
use App\Models\User;
use App\Support\PackageArchive;
use App\Support\ThemeContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Installs a theme or an extension from an uploaded .zip, or updates one an
 * earlier upload installed.
 *
 * In order: the archive is checked without unpacking it (PackageArchive),
 * the target folder is checked (never the default theme, never a folder the
 * store did not get from an upload, never a link, never an older version),
 * the archive is unpacked into a hidden folder beside the target, a theme
 * is checked against the theme contract, then the folder is swapped into
 * place with a rename and the package is installed through the same code as
 * the Install and Update buttons (ExtensionManager::install), which runs its
 * migrations. When installing fails, the files that were there before are
 * put back.
 *
 * Nothing from the archive runs before it is in place and installed. Every
 * upload, refused or not, is recorded in package_uploads and in the log.
 */
class PackageUploader
{
    /**
     * Names core uses for its own pages under extensions/, which an
     * extension's folder would otherwise shadow.
     */
    public const RESERVED_NAMES = ['modules', 'order-totals', 'upload', 'payments', 'shipping'];

    public function __construct(
        private ExtensionManager $extensions,
        private ThemeService $themes,
    ) {
    }

    /**
     * The limits an archive is checked against, in bytes and entries.
     *
     * @return array{max_upload_bytes: int, max_files: int, max_file_bytes: int, max_total_bytes: int}
     */
    public static function limits(): array
    {
        $mb = fn (string $key, int $default) => max(1, (int) config("extensions.upload.{$key}", $default)) * 1048576;

        return [
            'max_upload_bytes' => $mb('max_size_mb', 64),
            'max_files' => max(1, (int) config('extensions.upload.max_files', 5000)),
            'max_file_bytes' => $mb('max_file_mb', 32),
            'max_total_bytes' => $mb('max_unpacked_mb', 256),
        ];
    }

    /**
     * The largest upload this server accepts: the configured limit, or less
     * when PHP's own upload limits are lower.
     */
    public static function effectiveUploadLimit(): int
    {
        $limit = self::limits()['max_upload_bytes'];

        foreach (['upload_max_filesize', 'post_max_size'] as $ini) {
            $bytes = self::iniBytes((string) ini_get($ini));
            if ($bytes > 0) {
                $limit = min($limit, $bytes);
            }
        }

        return $limit;
    }

    /**
     * Installs or updates the package in $file and returns the record of it.
     *
     * @throws PackageRefused with a message for the admin when the package is not installed
     */
    public function upload(UploadedFile $file, ?User $user): PackageUpload
    {
        $record = new PackageUpload([
            'user_id' => $user?->id,
            'filename' => Str::limit(basename((string) $file->getClientOriginalName()), 250, ''),
            'size' => (int) $file->getSize(),
            'sha256' => (string) hash_file('sha256', (string) $file->getRealPath()),
            'result' => PackageUpload::REFUSED,
        ]);

        $lock = Cache::lock('package-uploads', 600);
        if (! $lock->get()) {
            return $this->refuse($record, new PackageRefused('Another package is being installed. Try again when it has finished.'), $user);
        }

        $work = null;

        try {
            $archive = PackageArchive::open((string) $file->getRealPath(), self::limits());

            $package = $archive->kind() === 'theme' ? ThemeService::registryName($archive->slug()) : $archive->slug();
            $record->fill([
                'kind' => $archive->kind(),
                'slug' => $archive->slug(),
                'package' => $package,
                'version' => $archive->version(),
            ]);

            $this->extensions->rescan();
            $target = $this->targetFor($archive);
            $this->assertTargetMayTakeIt($archive, $package, $target);
            $this->assertNamesAreFree($archive, $target);

            $installed = $this->extensions->registry()->get($package);
            $previous = $installed?->version;
            $record->previous_version = $previous;

            $work = $this->workFolder(dirname($target));
            $staged = $archive->extractTo($work . '/unpacked');

            $this->assertPackageIsComplete($archive, $staged);

            $backup = $this->swapIn($staged, $target, $work);

            try {
                $this->extensions->rescan();
                $this->extensions->install($package);
            } catch (\Throwable $e) {
                $this->swapBack($target, $backup, $work);
                $this->extensions->rescan();

                throw $e;
            }

            $this->afterFilesChanged();

            $record->result = $installed ? PackageUpload::UPDATED : PackageUpload::INSTALLED;
            $record->message = $this->summary($archive, $previous, (bool) $installed);
            $record->save();

            Log::info("Package upload: {$record->message}", $this->context($record, $user));

            return $record;
        } catch (PackageRefused $e) {
            return $this->refuse($record, $e, $user);
        } catch (\Throwable $e) {
            return $this->fail($record, $e, $user);
        } finally {
            if ($work !== null) {
                File::deleteDirectory($work);
            }
            $lock->release();
        }
    }

    private function targetFor(PackageArchive $archive): string
    {
        if ($archive->kind() === 'theme') {
            return resource_path('themes/' . $archive->slug());
        }

        $root = (string) config('extensions.path', base_path('extensions'));
        File::ensureDirectoryExists($root, 0755);

        return rtrim($root, '/') . '/' . $archive->slug();
    }

    /** @throws PackageRefused */
    private function assertTargetMayTakeIt(PackageArchive $archive, string $package, string $target): void
    {
        $slug = $archive->slug();
        $where = $archive->kind() === 'theme' ? "resources/themes/{$slug}" : "extensions/{$slug}";

        if ($archive->kind() === 'theme' && $slug === ThemeService::THEME) {
            throw new PackageRefused("\"{$slug}\" is the store's own default theme. A theme upload needs a folder name of its own.");
        }

        if ($archive->kind() === 'extension' && (in_array($slug, self::RESERVED_NAMES, true) || str_starts_with($slug, 'theme-'))) {
            throw new PackageRefused("\"{$slug}\" is a name the store keeps for its own pages. Give the extension another name.");
        }

        if (is_link($target)) {
            throw new PackageRefused("{$where} is a link to a folder elsewhere on the server, so an upload will not replace it. Update the files where the link points.");
        }

        if (! file_exists($target)) {
            return;
        }

        if (! is_dir($target) || ! PackageUpload::placed($package)) {
            throw new PackageRefused("{$where} is already on the server and was not installed by an upload, so an upload will not replace it. It may be part of the store itself.");
        }

        $manifestFile = $target . '/' . $archive->kind() . '.json';
        $current = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : null;
        $currentVersion = is_array($current) && is_string($current['version'] ?? null) ? $current['version'] : null;

        if ($currentVersion !== null && version_compare($archive->version(), $currentVersion, '<')) {
            throw new PackageRefused("This is {$archive->title()} {$archive->version()}, older than the {$currentVersion} on the server. Uninstall it first to go back to an older version.");
        }
    }

    /**
     * A package's names must not already belong to another package on disk:
     * discovery keys every package by name, so a second one would hide the
     * first.
     *
     * @throws PackageRefused
     */
    private function assertNamesAreFree(PackageArchive $archive, string $target): void
    {
        $discovered = $this->extensions->discovered();
        $ownedBy = function (string $name) use ($discovered, $target): bool {
            $path = $discovered->get($name)['path'] ?? null;

            return $path === null || $path === $target || str_starts_with((string) $path, $target . '/');
        };

        if ($archive->kind() === 'extension' && ! $ownedBy($archive->slug())) {
            throw new PackageRefused("Another package on the server is already called \"{$archive->slug()}\".");
        }

        foreach ($archive->bundledExtensions() as $folder => $manifest) {
            $name = $manifest['name'] ?? null;

            if (! is_string($name) || strlen($name) > PackageArchive::SLUG_MAX || ! preg_match(PackageArchive::SLUG_PATTERN, $name)) {
                throw new PackageRefused("extensions/{$folder}/extension.json needs a name of lowercase letters, digits and single hyphens.");
            }
            if (in_array($name, self::RESERVED_NAMES, true) || str_starts_with($name, 'theme-')) {
                throw new PackageRefused("extensions/{$folder} is called \"{$name}\", a name the store keeps for its own pages.");
            }
            if (isset($manifest['provider']) && ! PackageArchive::validProvider($manifest['provider'])) {
                throw new PackageRefused("extensions/{$folder}/extension.json names a provider class that an extension may not use.");
            }
            if (! $ownedBy($name)) {
                throw new PackageRefused("The package brings an extension called \"{$name}\", but another package on the server already uses that name.");
            }
        }
    }

    /** @throws PackageRefused */
    private function assertPackageIsComplete(PackageArchive $archive, string $staged): void
    {
        if ($archive->kind() === 'theme') {
            $problems = ThemeContract::check($staged);

            if ($problems !== []) {
                $shown = array_slice($problems, 0, 8);
                $more = count($problems) - count($shown);

                throw new PackageRefused($archive->title() . ' does not keep the theme contract: ' . implode(' ', $shown) . ($more > 0 ? " And {$more} more." : ''));
            }
        }

        $provider = $archive->manifest()['provider'] ?? null;
        if (is_string($provider)) {
            $class = substr($provider, (int) strrpos($provider, '\\') + 1);
            if (! is_file($staged . '/src/' . $class . '.php')) {
                throw new PackageRefused("The manifest names {$provider}, but src/{$class}.php is not in the package.");
            }
        }
    }

    /**
     * A hidden folder beside the target, so moving the package into place
     * is a rename on one file system. Discovery skips hidden folders.
     */
    private function workFolder(string $parent): string
    {
        if (! is_dir($parent) || ! is_writable($parent)) {
            throw new PackageRefused('The store cannot write to the folder packages are installed in. Give the web server write access to resources/themes and extensions, then upload again.');
        }

        $work = $parent . '/.upload-' . Str::lower(Str::random(12));
        File::ensureDirectoryExists($work . '/unpacked', 0755);

        return $work;
    }

    /** Moves $staged to $target and returns where the files it replaced went. */
    private function swapIn(string $staged, string $target, string $work): ?string
    {
        $backup = null;

        if (file_exists($target)) {
            $backup = $work . '/previous';
            if (! @rename($target, $backup)) {
                throw new \RuntimeException('The installed files could not be moved aside.');
            }
        }

        if (! @rename($staged, $target)) {
            if ($backup !== null) {
                @rename($backup, $target);
            }

            throw new \RuntimeException('The package could not be moved into place.');
        }

        return $backup;
    }

    /** Puts the files an upload replaced back, or removes a package that was new. */
    private function swapBack(string $target, ?string $backup, string $work): void
    {
        $failed = $work . '/failed';
        @rename($target, $failed);

        if ($backup !== null) {
            @rename($backup, $target);
        }
    }

    private function afterFilesChanged(): void
    {
        $this->themes->clearCache();

        // PHP may keep compiled copies of the files that were replaced.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function summary(PackageArchive $archive, ?string $previous, bool $updated): string
    {
        $what = $archive->kind() === 'theme' ? 'theme' : 'extension';

        if (! $updated) {
            return "{$archive->title()} {$archive->version()} ({$what}) is installed.";
        }

        return $previous === $archive->version()
            ? "{$archive->title()} {$archive->version()} ({$what}) is installed again over the same version."
            : "{$archive->title()} ({$what}) is updated from {$previous} to {$archive->version()}.";
    }

    private function refuse(PackageUpload $record, PackageRefused $e, ?User $user): never
    {
        $record->result = PackageUpload::REFUSED;
        $record->message = Str::limit($e->getMessage(), 2000);
        $record->save();

        Log::warning('Package upload refused: ' . $e->getMessage(), $this->context($record, $user));

        throw $e;
    }

    private function fail(PackageUpload $record, \Throwable $e, ?User $user): never
    {
        $title = $record->slug ?? 'The package';
        $message = "Installing {$title} failed, and the store kept the files it had before. Check the application log for details.";

        $record->result = PackageUpload::REFUSED;
        $record->message = $message;
        $record->save();

        // Driver and file system errors carry server details that belong
        // in the log, not on an admin screen.
        Log::error('Package upload failed', $this->context($record, $user) + ['exception' => $e]);

        throw new PackageRefused($message, 0, $e);
    }

    /** @return array<string, mixed> */
    private function context(PackageUpload $record, ?User $user): array
    {
        return [
            'user_id' => $user?->id,
            'user' => $user?->email,
            'file' => $record->filename,
            'sha256' => $record->sha256,
            'package' => $record->package,
            'version' => $record->version,
            'previous_version' => $record->previous_version,
        ];
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1' || $value === '0') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
