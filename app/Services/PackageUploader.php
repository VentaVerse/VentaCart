<?php

namespace App\Services;

use App\Exceptions\PackageInstallFailed;
use App\Exceptions\PackageRefused;
use App\Models\Extension;
use App\Models\PackageUpload;
use App\Models\User;
use App\Support\PackageArchive;
use App\Support\PackageAssets;
use App\Support\PackageLock;
use App\Support\ThemeContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Installs a theme or an extension from an uploaded .zip, or updates one an
 * earlier upload installed.
 *
 * In order: the record of the upload is saved as pending, the archive is
 * checked without unpacking it (PackageArchive), the target folder is
 * checked (never the default theme, never a folder the store did not get
 * from an upload, never a link, never an older version), the archive is
 * unpacked into a hidden folder beside the target, a theme is checked
 * against the theme contract, the target is checked again, then the folder
 * is swapped into place with a rename and the package is installed through
 * the same code as the Install and Update buttons (ExtensionManager::install),
 * which runs its migrations. A theme's own extensions that are installed
 * are updated with it.
 *
 * When installing fails, everything the upload changed is undone: the
 * migrations it ran are rolled back, the previous files go back, the
 * registry rows and the published build are what they were. The same undo
 * runs when PHP stops part way (a timeout or a fatal error), from a
 * shutdown handler, and from the next upload or visit to the Upload screen
 * when the process was killed outright. A journal file in the hidden folder
 * says what to undo.
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

    private const WORK_PREFIX = '.upload-';

    private const JOURNAL = 'journal.json';

    /** The upload this process is in the middle of, for the shutdown handler. */
    private static ?self $current = null;

    private static bool $shutdownRegistered = false;

    /** Memory set aside so the shutdown handler can run after PHP ran out. */
    private static string $reserve = '';

    /** What to undo while files are being swapped and installed, or null. */
    private ?array $inFlight = null;

    private ?PackageLock $lock = null;

    /** Set when an undo could not put the previous files back: the hidden folder holds them. */
    private bool $keepWork = false;

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
            'filename' => basename((string) $file->getClientOriginalName()),
            'size' => (int) $file->getSize(),
            'sha256' => (string) hash_file('sha256', (string) $file->getRealPath()),
            'result' => PackageUpload::PENDING,
        ]);

        $lock = PackageLock::acquire((float) config('extensions.upload.lock_wait_seconds', 10));
        if ($lock === null) {
            $this->refuse($record, new PackageRefused('Another package is being installed, updated or removed. Try again when it has finished.'), $user);
        }

        $this->lock = $lock;
        $this->prepareForLongWork();
        $work = null;

        try {
            try {
                $this->recoverInterrupted();
            } catch (\Throwable $e) {
                Log::error('Package upload: an interrupted upload could not be undone', ['exception' => $e]);
            }

            // Saved before anything on disk changes, so even an upload that
            // never finishes leaves a record.
            $record->save();

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
            $bundled = $this->assertNamesAreFree($archive, $target);
            $this->assertMigrationsAreFree($archive, $target);

            $installed = $this->extensions->registry()->get($package);
            $previous = $installed?->version;
            $record->previous_version = $previous;
            $record->save();

            $work = $this->workFolder(dirname($target));
            $this->writeJournal($work, ['record' => $record->id, 'stage' => 'unpacking']);
            $staged = $archive->extractTo($work . '/unpacked');

            $this->assertPackageIsComplete($archive, $staged, $bundled);

            // The checks above ran before the archive was unpacked, which
            // takes time. A folder may have appeared at the target since
            // (a deploy, a copy by hand), so look again before replacing.
            $this->extensions->rescan();
            $this->assertTargetMayTakeIt($archive, $package, $target);
            $this->assertNamesAreFree($archive, $target);
            if ($this->extensions->registry()->get($package)?->version !== $previous) {
                throw new PackageRefused('The installed version changed while the package was checked. Upload it again.');
            }

            $names = array_merge([$package], $bundled);
            $this->inFlight = [
                'record' => $record->id,
                'stage' => 'swapping',
                'result' => $installed ? PackageUpload::UPDATED : PackageUpload::INSTALLED,
                'package' => $package,
                'title' => $archive->title(),
                'target' => $target,
                'work' => $work,
                'staged' => $staged,
                'had_previous' => file_exists($target),
                'names' => $names,
                'registry' => $this->registrySnapshot($names),
                'batch' => $this->extensions->lastMigrationBatch(),
            ];
            $this->writeJournal($work, $this->inFlight);

            try {
                $this->swapIn($staged, $target, $work);

                $this->inFlight['stage'] = 'installing';
                $this->writeJournal($work, $this->inFlight);

                $this->extensions->rescan();
                $this->extensions->install($package);

                // A theme's own extensions run on the theme's new files at
                // once, so the installed ones are updated with it: their
                // migrations run and their version is recorded.
                foreach ($bundled as $name) {
                    if ($this->inFlight['registry'][$name] !== null) {
                        $this->extensions->install($name);
                    }
                }
            } catch (\Throwable $e) {
                $undo = $this->undo($this->inFlight);
                $this->inFlight = null;

                $this->fail($record, $e, $user, $undo, $undo['restored'] ? null : $work);
            }

            // Installed. Nothing from here on may undo it or record it as
            // anything else, so no error below reaches the catches.
            $record->result = $installed ? PackageUpload::UPDATED : PackageUpload::INSTALLED;

            try {
                $this->inFlight['stage'] = 'installed';
                $this->writeJournal($work, $this->inFlight);
            } catch (\Throwable $e) {
                Log::warning('Package upload: the journal of a finished install could not be written', ['exception' => $e]);
            }

            try {
                $record->message = $this->summary($archive, $previous, (bool) $installed);
                $this->saveResult($record);
                $this->inFlight = null;
                $this->afterFilesChanged();

                Log::info('Package upload: ' . $record->message, $this->context($record, $user));
            } catch (\Throwable $e) {
                $this->inFlight = null;
                Log::error('Package upload: an install worked, but a step after it failed', ['exception' => $e]);
            }

            return $record;
        } catch (PackageInstallFailed $e) {
            throw $e;
        } catch (PackageRefused $e) {
            $this->refuse($record, $e, $user);
        } catch (\Throwable $e) {
            $this->fail($record, $e, $user, null, null);
        } finally {
            if ($work !== null && is_dir($work) && ! $this->keepWork) {
                File::deleteDirectory($work);
            }
            $this->keepWork = false;
            $this->inFlight = null;
            self::$current = null;
            $this->lock = null;
            $lock->release();
        }
    }

    /**
     * Undoes uploads a process left part way: it was killed, or stopped
     * before its shutdown handler could finish. Runs under the lock, so no
     * other upload is in progress and every hidden work folder and pending
     * record belongs to one that is not running any more.
     */
    public function recoverInterrupted(): void
    {
        foreach ([ThemeService::root(), $this->extensionsRoot()] as $root) {
            foreach (glob($root . '/' . self::WORK_PREFIX . '*', GLOB_ONLYDIR) ?: [] as $work) {
                $journal = $this->readJournal($work);

                if ($journal === null || ($journal['stage'] ?? '') === 'unpacking') {
                    // Nothing outside this folder changed yet.
                    File::deleteDirectory($work);
                    if (isset($journal['record'])) {
                        $this->markInterrupted((int) $journal['record'], 'The upload stopped before the package was installed. Nothing was changed.');
                    }

                    continue;
                }

                $journal['work'] = $work;
                $this->finishInterrupted($journal);
            }
        }

        try {
            PackageUpload::where('result', PackageUpload::PENDING)->get()->each(function (PackageUpload $stale) {
                $stale->result = PackageUpload::FAILED;
                $stale->message = 'The upload stopped before it finished. Nothing was changed.';
                $stale->save();
            });
        } catch (\Throwable $e) {
            Log::error('Package upload: pending records could not be closed', ['exception' => $e]);
        }
    }

    /**
     * Runs when PHP shuts down. When an upload was in the middle of
     * swapping or installing (a timeout or a fatal error skips every
     * catch and finally), it is undone here, and the lock is released.
     */
    public static function handleShutdown(): void
    {
        self::$reserve = '';
        $uploader = self::$current;

        if ($uploader === null || $uploader->inFlight === null) {
            return;
        }

        @set_time_limit(120);
        $journal = $uploader->inFlight;
        $uploader->inFlight = null;

        // A migration that died mid-way leaves its transaction open, and
        // nothing written inside it would ever be committed.
        try {
            foreach (DB::getConnections() as $connection) {
                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
            }
        } catch (\Throwable) {
            // A connection that cannot roll back is reconnected on next use.
        }

        try {
            $uploader->finishInterrupted($journal, error_get_last()['message'] ?? null);
        } catch (\Throwable $e) {
            Log::critical('Package upload: undoing an interrupted upload failed', ['exception' => $e, 'work' => $journal['work'] ?? null]);
        } finally {
            $uploader->lock?->release();
        }
    }

    /**
     * Brings one interrupted upload to an end: an upload that had finished
     * installing is recorded as such, any other is undone.
     */
    private function finishInterrupted(array $journal, ?string $error = null): void
    {
        $work = (string) $journal['work'];
        $record = (int) ($journal['record'] ?? 0);

        if (($journal['stage'] ?? '') === 'installed') {
            $this->markResult($record, (string) ($journal['result'] ?? PackageUpload::INSTALLED));
            File::deleteDirectory($work);

            return;
        }

        $undo = $this->undo($journal);
        $title = PackageArchive::plain((string) ($journal['title'] ?? 'The package'), PackageArchive::TITLE_MAX);
        $message = "Installing {$title} stopped part way. " . $this->undoSentence($undo);

        $this->markInterrupted($record, $message);
        Log::error('Package upload interrupted: ' . $message, ['package' => $journal['package'] ?? null, 'error' => $error !== null ? PackageArchive::plain($error, 500) : null]);

        if ($undo['restored']) {
            File::deleteDirectory($work);
        } else {
            Log::critical('Package upload: the previous files could not be put back and are kept in ' . $work);
        }
    }

    /**
     * Undoes a swap and install: rolls back the migrations it ran, puts
     * the previous files back (or removes a package that was new), and
     * restores the registry rows and the published builds.
     *
     * @return array{restored: bool, migrations: bool}
     */
    private function undo(array $journal): array
    {
        $target = (string) $journal['target'];
        $work = (string) $journal['work'];
        $backup = $work . '/previous';
        $names = (array) ($journal['names'] ?? []);
        $migrations = true;

        // The new files hold the down() of the migrations the new version
        // ran, so they roll back while those files are still in place.
        if (is_dir($target) && $this->targetIsNew($journal)) {
            try {
                $paths = array_merge([$target], glob($target . '/extensions/*', GLOB_ONLYDIR) ?: []);
                $this->extensions->rollBackMigrationsAfter((int) ($journal['batch'] ?? PHP_INT_MAX), $paths);
            } catch (\Throwable $e) {
                $migrations = false;
                Log::error('Package upload: rolling back the migrations of a failed install failed', ['exception' => $e, 'package' => $journal['package'] ?? null]);
            }
        }

        $restored = true;

        if (is_dir($backup)) {
            if (file_exists($target) && ! @rename($target, $work . '/failed')) {
                $restored = false;
            } elseif (! @rename($backup, $target)) {
                $restored = false;
            }
        } elseif ($this->targetIsNew($journal) && file_exists($target)) {
            $restored = @rename($target, $work . '/failed');
        }

        try {
            foreach ((array) ($journal['registry'] ?? []) as $name => $row) {
                if ($row === null) {
                    Extension::where('name', $name)->delete();
                } else {
                    Extension::updateOrCreate(['name' => $name], [
                        'version' => $row['version'],
                        'enabled' => (bool) $row['enabled'],
                        'installed_at' => $row['installed_at'],
                    ]);
                }
            }
            $this->extensions->forgetRegistry();
        } catch (\Throwable $e) {
            Log::error('Package upload: the registry could not be put back', ['exception' => $e, 'package' => $journal['package'] ?? null]);
        }

        if ($restored) {
            $this->extensions->rescan();
            foreach ($names as $name) {
                $path = $this->extensions->discovered()->get($name)['path'] ?? null;

                try {
                    if (($journal['registry'][$name] ?? null) !== null && is_string($path)) {
                        PackageAssets::publish($name, $path);
                    } else {
                        PackageAssets::remove($name);
                    }
                } catch (\Throwable $e) {
                    Log::error('Package upload: the earlier build could not be published again', ['exception' => $e, 'package' => $name]);
                }
            }
        }

        $this->afterFilesChanged();

        return ['restored' => $restored, 'migrations' => $migrations];
    }

    /** Whether the folder at the target is the uploaded one (it was moved in). */
    private function targetIsNew(array $journal): bool
    {
        $work = (string) $journal['work'];

        if (is_dir($work . '/previous')) {
            return true;
        }

        // A new install: the unpacked folder left its place when it moved in.
        return empty($journal['had_previous']) && ! file_exists((string) ($journal['staged'] ?? $work . '/unpacked/missing'));
    }

    private function undoSentence(array $undo): string
    {
        if (! $undo['restored']) {
            return 'The store could not put the previous files back; they are kept in a hidden folder beside the package. Check the application log.';
        }

        return $undo['migrations']
            ? 'The store put back the files, database tables and settings it had before.'
            : 'The store put back the files it had before, but could not undo every database change. Check the application log.';
    }

    private function targetFor(PackageArchive $archive): string
    {
        if ($archive->kind() === 'theme') {
            File::ensureDirectoryExists(ThemeService::root(), 0755);

            return ThemeService::root($archive->slug());
        }

        $root = $this->extensionsRoot();
        File::ensureDirectoryExists($root, 0755);

        return $root . '/' . $archive->slug();
    }

    private function extensionsRoot(): string
    {
        return rtrim((string) config('extensions.path', base_path('extensions')), '/');
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

        $current = $this->manifestVersion($target . '/' . $archive->kind() . '.json');

        if ($current !== null && version_compare($archive->version(), $current, '<')) {
            throw new PackageRefused("This is {$archive->title()} {$archive->version()}, older than the {$current} on the server. Uninstall it first to go back to an older version.");
        }
    }

    /**
     * A package's names and namespaces must not already belong to another
     * package on disk: discovery keys every package by name, so a second
     * one would hide the first, and a namespace two packages share lets
     * one supply the other's classes. Returns the names of the extensions
     * the package brings.
     *
     * @return list<string>
     *
     * @throws PackageRefused
     */
    private function assertNamesAreFree(PackageArchive $archive, string $target): array
    {
        $discovered = $this->extensions->discovered();
        $ownedBy = function (string $name) use ($discovered, $target): bool {
            $path = $discovered->get($name)['path'] ?? null;

            return $path === null || $this->inside((string) $path, $target);
        };

        if ($archive->kind() === 'extension' && ! $ownedBy($archive->slug())) {
            throw new PackageRefused("Another package on the server is already called \"{$archive->slug()}\".");
        }

        $provider = $archive->manifest()['provider'] ?? null;
        if (is_string($provider)) {
            $this->assertNamespaceIsFree($provider, $target, $archive->kind() . '.json');
        }

        $names = [];

        foreach ($archive->bundledExtensions() as $folder => $manifest) {
            $name = $manifest['name'] ?? null;
            $file = 'extensions/' . PackageArchive::plain((string) $folder, 80) . '/extension.json';

            if (! is_string($name) || strlen($name) > PackageArchive::SLUG_MAX || ! preg_match(PackageArchive::SLUG_PATTERN, $name)) {
                throw new PackageRefused("{$file} needs a name of lowercase letters, digits and single hyphens.");
            }
            if (in_array($name, self::RESERVED_NAMES, true) || str_starts_with($name, 'theme-')) {
                throw new PackageRefused("{$file} names the extension \"{$name}\", a name the store keeps for its own pages.");
            }
            if (isset($manifest['provider'])) {
                if (! PackageArchive::validProvider($manifest['provider'])) {
                    throw new PackageRefused("{$file} names a provider class that an extension may not use.");
                }
                $this->assertNamespaceIsFree($manifest['provider'], $target, $file);
            }
            if (! $ownedBy($name) || in_array($name, $names, true)) {
                throw new PackageRefused("The package brings an extension called \"{$name}\", but another package on the server already uses that name.");
            }

            $version = $manifest['version'] ?? null;
            if (! is_string($version) || strlen($version) > PackageArchive::VERSION_MAX || ! preg_match(PackageArchive::VERSION_PATTERN, $version)) {
                throw new PackageRefused("{$file} needs a version such as 1.0.0, of at most " . PackageArchive::VERSION_MAX . ' characters.');
            }

            // Its tables may already be on a newer schema than the code
            // this package brings for it.
            $row = $this->extensions->registry()->get($name);
            if ($row !== null && version_compare($version, (string) $row->version, '<')) {
                throw new PackageRefused("The package brings {$name} {$version}, older than the {$row->version} installed. Uninstall {$name} first to go back to an older version.");
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * A provider's namespace must not be, hold or sit inside a namespace a
     * library the store runs on uses, or one another package's provider
     * uses. Compared without regard to case.
     *
     * @throws PackageRefused
     */
    private function assertNamespaceIsFree(string $provider, string $target, string $file): void
    {
        $namespace = PackageArchive::providerNamespace($provider);

        foreach (self::libraryNamespaces() as $taken) {
            if (PackageArchive::namespacesOverlap($namespace, $taken)) {
                throw new PackageRefused("{$file} names a provider in the namespace of a library the store runs on ({$taken}). Use a namespace of the package's own.");
            }
        }

        foreach ($this->extensions->discovered() as $other) {
            $theirs = $other['provider'] ?? null;

            if (! is_string($theirs) || $this->inside((string) ($other['path'] ?? ''), $target)) {
                continue;
            }

            if (PackageArchive::namespacesOverlap($namespace, PackageArchive::providerNamespace($theirs))) {
                $title = PackageArchive::plain((string) ($other['title'] ?? $other['name'] ?? 'another package'), PackageArchive::TITLE_MAX);

                throw new PackageRefused("{$file} names a provider in the namespace {$title} already uses. Use a namespace of the package's own.");
            }
        }
    }

    /**
     * Namespaces Composer loads for the store and its libraries.
     *
     * @return list<string>
     */
    private static function libraryNamespaces(): array
    {
        static $namespaces = null;

        if ($namespaces === null) {
            $namespaces = [];

            foreach (['autoload_psr4.php', 'autoload_namespaces.php'] as $map) {
                $file = base_path('vendor/composer/' . $map);
                if (is_file($file)) {
                    $loaded = require $file;
                    $namespaces = array_merge($namespaces, array_filter(array_keys(is_array($loaded) ? $loaded : []), fn ($key) => is_string($key) && $key !== ''));
                }
            }

            $namespaces = array_values(array_unique($namespaces));
        }

        return $namespaces;
    }

    /**
     * Laravel knows a migration by its file name alone. A package migration
     * named like one of core's or another package's would be skipped as
     * already run, and removing the package would roll back the other
     * one's. Such a package is refused.
     *
     * @throws PackageRefused
     */
    private function assertMigrationsAreFree(PackageArchive $archive, string $target): void
    {
        $theirs = $archive->migrationNames();
        if ($theirs === []) {
            return;
        }

        $taken = [];
        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            $taken[basename($file, '.php')] = 'the store itself';
        }

        $own = [];
        foreach ($this->extensions->discovered() as $other) {
            $path = (string) ($other['path'] ?? '');
            $names = array_map(fn ($file) => basename($file, '.php'), glob($path . '/database/migrations/*.php') ?: []);

            if ($this->inside($path, $target)) {
                $own = array_merge($own, $names);

                continue;
            }

            foreach ($names as $name) {
                $taken[$name] ??= PackageArchive::plain((string) ($other['title'] ?? $other['name'] ?? 'another package'), PackageArchive::TITLE_MAX);
            }
        }

        $ran = [];
        try {
            $repository = app('migration.repository');
            $ran = $repository->repositoryExists() ? array_flip($repository->getRan()) : [];
        } catch (\Throwable) {
            // No migrations table yet: nothing has run.
        }

        $seen = [];
        foreach ($theirs as ['name' => $name]) {
            $shown = PackageArchive::plain($name, 120);

            if (isset($seen[$name])) {
                throw new PackageRefused("The package brings two migrations called {$shown}. Give each a name of its own.");
            }
            if (isset($taken[$name])) {
                throw new PackageRefused("The migration {$shown} has the same name as one of {$taken[$name]}. Give it a name of its own.");
            }
            if (isset($ran[$name]) && ! in_array($name, $own, true)) {
                throw new PackageRefused("A migration called {$shown} has already run on this store from somewhere else. Give it a name of its own.");
            }
            $seen[$name] = true;
        }
    }

    /**
     * The checks on the unpacked package, the last step before the target
     * is looked at again and the package is swapped in.
     *
     * @param  list<string>  $bundled
     *
     * @throws PackageRefused
     */
    protected function assertPackageIsComplete(PackageArchive $archive, string $staged, array $bundled): void
    {
        if ($archive->kind() === 'theme') {
            $problems = ThemeContract::check($staged);

            if ($problems !== []) {
                $shown = array_map(fn ($problem) => PackageArchive::plain((string) $problem, 300), array_slice($problems, 0, 8));
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

        $builds = array_merge(['' => $staged], array_combine(
            array_map(fn ($folder) => 'extensions/' . basename($folder) . '/', glob($staged . '/extensions/*', GLOB_ONLYDIR) ?: []),
            glob($staged . '/extensions/*', GLOB_ONLYDIR) ?: [],
        ) ?: []);

        foreach ($builds as $prefix => $path) {
            $dangerous = PackageAssets::dangerousFiles($path);

            if ($dangerous !== []) {
                $shown = implode(', ', array_map(fn ($file) => PackageArchive::plain($prefix . 'build/' . $file, 120), array_slice($dangerous, 0, 5)));

                throw new PackageRefused("The package's build/ folder holds files the store will not publish, because a web server could run them or they can run script in the browser: {$shown}. A build holds stylesheets, scripts, fonts and raster images only.");
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

        $work = $parent . '/' . self::WORK_PREFIX . Str::lower(Str::random(12));
        File::ensureDirectoryExists($work . '/unpacked', 0755);

        return $work;
    }

    /**
     * Moves $staged to $target; the files it replaces go to $work/previous.
     * A failure part way is undone by undo(), like any failed install.
     */
    private function swapIn(string $staged, string $target, string $work): void
    {
        if (file_exists($target) && ! @rename($target, $work . '/previous')) {
            throw new \RuntimeException('The installed files could not be moved aside.');
        }

        if (! @rename($staged, $target)) {
            throw new \RuntimeException('The package could not be moved into place.');
        }
    }

    /**
     * Registry rows of these packages as they are before the upload, null
     * for one that is not installed.
     *
     * @param  list<string>  $names
     * @return array<string, array{version: string, enabled: bool, installed_at: ?string}|null>
     */
    private function registrySnapshot(array $names): array
    {
        $rows = Extension::whereIn('name', $names)->get()->keyBy('name');
        $snapshot = [];

        foreach ($names as $name) {
            $row = $rows->get($name);
            $snapshot[$name] = $row === null ? null : [
                'version' => (string) $row->version,
                'enabled' => (bool) $row->enabled,
                'installed_at' => $row->installed_at?->toDateTimeString(),
            ];
        }

        return $snapshot;
    }

    private function writeJournal(string $work, array $journal): void
    {
        if (@file_put_contents($work . '/' . self::JOURNAL, json_encode($journal, JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new \RuntimeException('The upload journal could not be written.');
        }
    }

    private function readJournal(string $work): ?array
    {
        $journal = json_decode((string) @file_get_contents($work . '/' . self::JOURNAL), true);

        if (! is_array($journal)) {
            return null;
        }

        // A journal is trusted only for paths beside the folder it is in.
        $parent = dirname($work);
        foreach (['target', 'staged'] as $key) {
            if (isset($journal[$key]) && ! $this->inside((string) $journal[$key], $parent)) {
                return null;
            }
        }

        return $journal;
    }

    /**
     * Raises the time limit, keeps going when the admin closes the page,
     * and makes sure an upload cut short by PHP is undone at shutdown.
     */
    private function prepareForLongWork(): void
    {
        @ignore_user_abort(true);

        $limit = (int) ini_get('max_execution_time');
        $wanted = max(0, (int) config('extensions.upload.max_seconds', 900));
        // 0 is no limit; never lower a limit, only raise it.
        if ($limit !== 0 && ($wanted === 0 || $wanted > $limit)) {
            @set_time_limit($wanted);
        }

        self::$current = $this;
        self::$reserve = str_repeat(' ', 262144);

        if (! self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'handleShutdown']);
            self::$shutdownRegistered = true;
        }
    }

    /** Saves the result of an install that worked, however the save goes. */
    private function saveResult(PackageUpload $record): void
    {
        try {
            $record->save();
        } catch (\Throwable $e) {
            Log::error('Package upload: the record of an install could not be saved in full', ['exception' => $e]);
            $this->markResult((int) $record->id, (string) $record->result);
        }
    }

    private function markResult(int $id, string $result): void
    {
        try {
            DB::table('package_uploads')->where('id', $id)->update(['result' => $result, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::critical('Package upload: the result of an upload could not be recorded', ['exception' => $e, 'id' => $id, 'result' => $result]);
        }
    }

    private function markInterrupted(int $id, string $message): void
    {
        try {
            DB::table('package_uploads')->where('id', $id)->update([
                'result' => PackageUpload::FAILED,
                'message' => PackageArchive::plain($message, PackageUpload::LENGTHS['message']),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Package upload: an interrupted upload could not be recorded', ['exception' => $e, 'id' => $id]);
        }
    }

    private function afterFilesChanged(): void
    {
        try {
            $this->themes->clearCache();
        } catch (\Throwable $e) {
            Log::warning('Package upload: the theme cache could not be cleared', ['exception' => $e]);
        }

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
        $message = PackageArchive::plain($e->getMessage(), PackageUpload::LENGTHS['message']);
        $record->result = PackageUpload::REFUSED;
        $record->message = $message;

        try {
            $record->save();
        } catch (\Throwable $saveError) {
            Log::error('Package upload: the record of a refused upload could not be saved', ['exception' => $saveError]);
        }

        Log::warning('Package upload refused: ' . $message, $this->context($record, $user));

        throw new PackageRefused($message, 0, $e);
    }

    /**
     * Records an install that failed and was undone, then throws the
     * message for the admin. Driver and file system errors carry server
     * details that belong in the log, not on an admin screen.
     *
     * @param  array{restored: bool, migrations: bool}|null  $undo  null when nothing was swapped yet
     */
    private function fail(PackageUpload $record, \Throwable $e, ?User $user, ?array $undo, ?string $keptIn): never
    {
        if ($keptIn !== null) {
            $this->keepWork = true;
            Log::critical('Package upload: the previous files could not be put back and are kept in ' . $keptIn);
        }

        $title = $record->slug ?? 'the package';
        $message = "Installing {$title} failed. " . ($undo === null ? 'Nothing on the store was changed.' : $this->undoSentence($undo));
        if ($undo === null || $undo['restored']) {
            $message .= ' Check the application log for details.';
        }

        $record->result = PackageUpload::FAILED;
        $record->message = $message;

        try {
            $record->save();
        } catch (\Throwable $saveError) {
            Log::error('Package upload: the record of a failed upload could not be saved', ['exception' => $saveError]);
            if ($record->exists) {
                $this->markInterrupted((int) $record->id, $message);
            }
        }

        Log::error('Package upload failed: ' . PackageArchive::plain($message, 500), $this->context($record, $user) + ['exception' => $e]);

        throw new PackageInstallFailed($record->message ?? $message, 0, $e);
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

    private function manifestVersion(string $file): ?string
    {
        $manifest = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($manifest) && is_string($manifest['version'] ?? null) ? $manifest['version'] : null;
    }

    /** Whether $path is $folder or inside it. */
    private function inside(string $path, string $folder): bool
    {
        $folder = rtrim($folder, '/');

        return $path !== '' && ($path === $folder || str_starts_with($path, $folder . '/'));
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
