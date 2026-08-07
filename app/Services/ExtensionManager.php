<?php

namespace App\Services;

use App\Models\Extension;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Extension kernel: discovers extension.json manifests on disk, cross-references
 * the `extensions` DB registry (the oc_extension analog), and boots the provider
 * for every installed+enabled extension.
 *
 * Filesystem presence + DB registry = the truth (OpenCart's model). Neither
 * bootstrap/providers.php nor root composer.json are edited per-extension: this
 * class registers a runtime PSR-4 autoloader (see registerAutoloader()) for each
 * enabled extension's namespace instead, because Composer's PSR-4 map has no
 * wildcard syntax and hand-adding an entry per extension would recouple the
 * exact thing this kernel removes.
 */
class ExtensionManager
{
    protected ?Collection $discoveredCache = null;

    /**
     * True once bootEnabled() has completed a full pass against a present
     * `extensions` table. Short-circuits repeat work within the same
     * long-lived instance (e.g. re-invoked per-request by
     * App\Http\Middleware\BootExtensions) — see that class for why a
     * per-request re-check exists at all.
     */
    protected bool $booted = false;

    /**
     * Manifests found on disk, keyed by extension name. Cached per-instance
     * (i.e. per request under normal singleton binding) since the filesystem
     * doesn't change mid-request.
     */
    public function discovered(): Collection
    {
        if ($this->discoveredCache !== null) {
            return $this->discoveredCache;
        }

        $root = $this->path();

        if (! File::isDirectory($root)) {
            return $this->discoveredCache = collect();
        }

        return $this->discoveredCache = collect(File::glob($root . '/*/extension.json'))
            ->map(function (string $manifestPath) {
                $manifest = json_decode(File::get($manifestPath), true);

                if (! is_array($manifest) || empty($manifest['name'])) {
                    return null;
                }

                $manifest['path'] = dirname($manifestPath);

                return $manifest;
            })
            ->filter()
            ->keyBy('name');
    }

    /**
     * DB registry rows, keyed by extension name. Empty-safe pre-migration
     * (fresh install mid-`migrate`, or migrations rolled back) so nothing in
     * the boot path ever crashes on a missing table.
     */
    public function registry(): Collection
    {
        if (! Schema::hasTable('extensions')) {
            return collect();
        }

        return Extension::all()->keyBy('name');
    }

    /**
     * Boots the provider for every installed+enabled extension. Called from
     * ExtensionServiceProvider::register() so provider boot() hooks (routes,
     * views, nav merges) run inside the normal provider lifecycle.
     *
     * Table-missing and manifest-missing extensions are silently skipped —
     * never crash the boot path over an extension's state.
     */
    public function bootEnabled(): void
    {
        if ($this->booted) {
            return;
        }

        if (! Schema::hasTable('extensions')) {
            return;
        }

        $bootedAny = false;

        foreach ($this->registry() as $row) {
            if (! $row->enabled) {
                continue;
            }

            $manifest = $this->discovered()->get($row->name);

            if (! $manifest || empty($manifest['provider'])) {
                continue; // missing_files — nothing on disk to boot
            }

            $providerClass = $manifest['provider'];

            $this->registerAutoloader($providerClass, $manifest['path']);

            if (class_exists($providerClass)) {
                app()->register($providerClass);
                $bootedAny = true;
            }
        }

        // Route name lookups are normally rebuilt exactly once, by the
        // framework's own routing bootstrap, after all *statically declared*
        // providers have booted. Extension providers are registered
        // dynamically (possibly after that point — e.g. when the `extensions`
        // table only becomes queryable partway through a request/test
        // lifecycle), so any named routes they add would silently fail
        // `Route::has()`/`route()` lookups without an explicit refresh here.
        if ($bootedAny && app()->bound('router')) {
            app('router')->getRoutes()->refreshNameLookups();
        }

        $this->booted = true;
    }

    /**
     * State of a named extension: not_installed|enabled|disabled|missing_files|needs_migration.
     */
    public function state(string $name): string
    {
        $manifest = $this->discovered()->get($name);
        $row = $this->registry()->get($name);

        if (! $row) {
            return 'not_installed';
        }

        if (! $manifest) {
            return 'missing_files';
        }

        if (! empty($manifest['version']) && version_compare($manifest['version'], $row->version, '>')) {
            return 'needs_migration';
        }

        return $row->enabled ? 'enabled' : 'disabled';
    }

    /**
     * Validates the manifest, runs the extension's migrations, then upserts
     * the registry row (enabled). TEST-ONLY against the live install until the
     * owner runs it deliberately — this method itself never touches the live
     * DB unless explicitly invoked.
     */
    public function install(string $name): void
    {
        $manifest = $this->discovered()->get($name);

        if (! $manifest) {
            throw new \RuntimeException("Cannot install extension [{$name}]: no extension.json found.");
        }

        $this->runMigrations($manifest['path'], 'migrate');

        Extension::updateOrCreate(
            ['name' => $name],
            [
                'version' => $manifest['version'] ?? '0.0.0',
                'enabled' => true,
                'installed_at' => now(),
            ]
        );

        // Let a subsequent bootEnabled() call (e.g. later in the same
        // request/test) pick up the freshly-installed extension instead of
        // short-circuiting on the cached $booted flag.
        $this->booted = false;
    }

    public function enable(string $name): void
    {
        $extension = Extension::where('name', $name)->firstOrFail();
        $extension->update(['enabled' => true]);

        $this->booted = false;
    }

    public function disable(string $name): void
    {
        $extension = Extension::where('name', $name)->firstOrFail();
        $extension->update(['enabled' => false]);

        // Note: this cannot retroactively un-register routes/views a provider
        // already booted earlier in this same process — that's an inherent
        // limitation of Laravel's provider model, not something resetting
        // this flag can fix. Disabling takes full effect on the next request
        // (new process/instance in real deployments).
    }

    /**
     * Rolls back the extension's own migrations, then deletes its registry
     * row. Core never migrates an extension's tables outside this path.
     */
    public function uninstall(string $name): void
    {
        $manifest = $this->discovered()->get($name);

        if ($manifest) {
            $this->runMigrations($manifest['path'], 'migrate:rollback');
        }

        Extension::where('name', $name)->delete();
    }

    /**
     * Registers a runtime PSR-4 autoloader mapping the provider's namespace
     * (everything before its class name) to the extension's src/ directory,
     * e.g. "Extensions\Banners\BannerServiceProvider" + ".../extensions/banners"
     * maps "Extensions\Banners\" -> ".../extensions/banners/src/".
     *
     * Safe to call repeatedly (registering the same closure twice just adds a
     * second, functionally-identical entry to the SPL stack). Note the
     * inverse is NOT true: spl_autoload_register is process-global, not
     * request-scoped — in a long-lived process (this test suite, Octane) a
     * class loaded once here stays defined for the rest of the process even
     * if the extension is later disabled/uninstalled. That's a hard PHP
     * constraint (no "undefine class"), not something this method controls.
     * Anything that uses class_exists() on an extension's classes as a proxy
     * for "currently installed" must pair it with a real state check (see
     * resources/views/admin/theme/section-config.blade.php for the pattern).
     */
    protected function registerAutoloader(string $providerClass, string $extensionPath): void
    {
        $segments = explode('\\', $providerClass);
        array_pop($segments); // drop the class name itself
        $namespace = implode('\\', $segments) . '\\';
        $srcPath = rtrim($extensionPath, '/') . '/src/';

        spl_autoload_register(function (string $class) use ($namespace, $srcPath) {
            if (! str_starts_with($class, $namespace)) {
                return;
            }

            $relative = substr($class, strlen($namespace));
            $file = $srcPath . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }

    protected function runMigrations(string $extensionPath, string $command): void
    {
        $migrationsPath = rtrim($extensionPath, '/') . '/database/migrations';

        if (! File::isDirectory($migrationsPath)) {
            return;
        }

        Artisan::call($command, [
            '--path' => $this->relativePath($migrationsPath),
            '--force' => true,
        ]);
    }

    protected function relativePath(string $absolute): string
    {
        return ltrim(str_replace(base_path(), '', $absolute), '/');
    }

    protected function path(): string
    {
        return config('extensions.path', base_path('extensions'));
    }
}
