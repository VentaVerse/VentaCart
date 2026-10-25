<?php

namespace App\Services;

use App\Models\Extension;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
    /**
     * Cache key for the DB registry snapshot (see registry()). Cross-request
     * (backed by the real cache store — config('cache.default') — not
     * in-process state), because $booted below only helps within a single
     * long-lived instance (Octane), never in the stateless PHP-FPM/CLI model
     * this app actually deploys under, where every request is a fresh
     * Application and would otherwise pay a Schema::hasTable() + table read
     * on every single storefront request (BootExtensions is global
     * middleware, not admin-only).
     */
    public const REGISTRY_CACHE_KEY = 'extensions.registry';

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

        $manifests = File::isDirectory($root)
            ? collect(File::glob($root . '/*/extension.json'))
                ->map(function (string $manifestPath) {
                    $manifest = json_decode(File::get($manifestPath), true);

                    if (! is_array($manifest) || empty($manifest['name'])) {
                        return null;
                    }

                    $manifest['path'] = dirname($manifestPath);

                    return $manifest;
                })
                ->filter()
            : collect();

        // A theme is a package too: a folder under resources/themes with a
        // theme.json. It has no provider, no migrations and no routes; the
        // registry row is what "installed" means, and the shipped default
        // is installed with the other essentials on a fresh store.
        $themes = collect(File::glob(resource_path('themes/*/theme.json')))
            ->map(function (string $json) {
                $meta = json_decode(File::get($json), true);

                if (! is_array($meta)) {
                    return null;
                }

                $slug = basename(dirname($json));

                return [
                    'name' => ThemeService::registryName($slug),
                    'title' => (string) ($meta['name'] ?? ucfirst(str_replace('-', ' ', $slug))),
                    'version' => (string) ($meta['version'] ?? '1.0.0'),
                    'description' => (string) ($meta['description'] ?? ''),
                    'type' => 'theme',
                    'theme' => $slug,
                    // A theme with code of its own (an editor, routes) names
                    // its provider like an extension does; src/ autoloads.
                    'provider' => $meta['provider'] ?? null,
                    'icon' => 'tags',
                    'path' => dirname($json),
                    'default_enabled' => $slug === ThemeService::THEME,
                ];
            })
            ->filter();

        // A theme may bring extensions of its own, under its extensions/
        // folder. They are packages like any other, installed and switched
        // through the same registry; they carry the theme's slug so the
        // theme's own panel can list them and nothing else claims them.
        $owned = collect(File::glob(resource_path('themes/*/extensions/*/extension.json')))
            ->map(function (string $manifestPath) {
                $manifest = json_decode(File::get($manifestPath), true);

                if (! is_array($manifest) || empty($manifest['name'])) {
                    return null;
                }

                $manifest['path'] = dirname($manifestPath);
                $manifest['for_theme'] = basename(dirname($manifestPath, 3));

                return $manifest;
            })
            ->filter();

        return $this->discoveredCache = $manifests->concat($owned)->concat($themes)->keyBy('name');
    }

    /** Forget what discovered() found, so the next call reads the disk again. */
    public function rescan(): void
    {
        $this->discoveredCache = null;
    }

    /**
     * DB registry rows, keyed by extension name. Empty-safe pre-migration
     * (fresh install mid-`migrate`, or migrations rolled back) so nothing in
     * the boot path ever crashes on a missing table.
     *
     * Cross-request cached (see REGISTRY_CACHE_KEY) — on a cache HIT this
     * does no Schema/DB work at all; the `Schema::hasTable` guard only runs
     * on a cache MISS. install()/enable()/disable()/uninstall() invalidate
     * the cache; nothing else needs to.
     *
     * CRITICAL, do not revert to Eloquent statics (Extension::all()/
     * ::where()) anywhere in this method: this is called from
     * bootEnabled(), which ExtensionServiceProvider::register() calls
     * directly — i.e. it can run before
     * Illuminate\Database\DatabaseServiceProvider::boot() has wired
     * Eloquent's connection resolver (Model::setConnectionResolver()
     * happens in ITS boot(), not register()). Any Eloquent static call here
     * throws "Call to a member function connection() on null", which — since
     * it happens during another provider's register() — aborts the entire
     * application bootstrap and 500s EVERY request, not just this one
     * extension. DB::table() and Model::newFromBuilder() (see
     * hydrateRegistryRows()) only touch the 'db' container binding and
     * plain property assignment respectively, both of which are safe
     * during register(). Regression test:
     * tests/Feature/AdminV2/ExtensionKernelBootSafetyTest.php — it boots a
     * genuinely separate Application process against a migrated, populated
     * `extensions` table, which is the one scenario RefreshDatabase-based
     * tests structurally cannot reproduce (see that test's docblock).
     */
    public function registry(): Collection
    {
        try {
            if (Cache::has(self::REGISTRY_CACHE_KEY)) {
                return Cache::get(self::REGISTRY_CACHE_KEY);
            }
        } catch (\Throwable) {
            // Cache backend unavailable/unmigrated (e.g. `database` store
            // without a migrated `cache` table) — fall through to a live,
            // guarded read below. Never let a broken cache take the site
            // down any more than a broken `extensions` table should.
        }

        if (! Schema::hasTable('extensions')) {
            // Deliberately NOT cached: a "table missing" snapshot (e.g.
            // captured mid `php artisan migrate`, before this migration has
            // run yet) must never persist past the moment the table starts
            // existing. Nothing would invalidate a bad cached-empty result —
            // install()/enable()/etc. are the only invalidation points, and
            // none of them run during a bare `migrate`.
            return collect();
        }

        $fresh = $this->hydrateRegistryRows(DB::table('extensions')->get());

        try {
            Cache::forever(self::REGISTRY_CACHE_KEY, $fresh);
        } catch (\Throwable) {
            // Cache write failed — the freshly-read value for THIS call is
            // still correct; we've just lost cross-request caching until
            // the backend recovers.
        }

        return $fresh;
    }

    /**
     * Turns raw `extensions` table rows (stdClass, from the query builder)
     * into real Extension model instances WITHOUT ever calling
     * Extension::query()/newQuery() — see registry()'s docblock for why that
     * matters. Model::newFromBuilder() only sets plain properties/attributes
     * and no-ops its "retrieved" event if no dispatcher is wired yet, so it's
     * safe at any point in the boot lifecycle.
     */
    protected function hydrateRegistryRows(iterable $rows): Collection
    {
        $prototype = new Extension();

        return collect($rows)
            ->map(fn ($row) => $prototype->newFromBuilder((array) $row))
            ->keyBy('name');
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
    /**
     * Admin-nav entries contributed by ENABLED extensions' manifests
     * (extension.json "admin_nav"). This is how an extension appears in
     * the sidebar without touching a core file: the entry lives in its
     * own folder, the registry merges it at runtime (AdminNav::registry()),
     * and disabling the extension removes it because this reads only
     * enabled state. Fail-open to []: nav assembly must never be the
     * thing that takes the admin down.
     *
     * @return array<int, array<string, mixed>>
     */
    public function navContributions(): array
    {
        try {
            return $this->discovered()
                ->filter(fn (array $m) => is_array($m['admin_nav'] ?? null)
                    && $this->state($m['name']) === 'enabled')
                ->flatMap(fn (array $m) => array_values(array_filter(
                    $m['admin_nav'],
                    fn ($entry) => is_array($entry),
                )))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

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
     * the registry row. A first install is enabled; an install over an
     * existing row (an update, state needs_migration) keeps its switch.
     */
    public function install(string $name): void
    {
        $manifest = $this->discovered()->get($name);

        if (! $manifest) {
            throw new \RuntimeException("Cannot install extension [{$name}]: no extension.json found.");
        }

        // A theme is installed only when it keeps the contract; a package
        // missing a view the store renders would take the storefront down
        // the moment it went live.
        if (! empty($manifest['theme'])) {
            $problems = \App\Support\ThemeContract::check($manifest['path']);

            if ($problems !== []) {
                throw new \RuntimeException(
                    ($manifest['title'] ?? $name) . ' does not keep the theme contract: ' . implode(' ', $problems)
                );
            }
        }

        $this->runMigrations($manifest['path'], 'migrate');

        // A package sold as a download carries its built stylesheets and
        // scripts; they go where the browser can load them.
        \App\Support\PackageAssets::publish($name, $manifest['path']);

        // Installing over an existing row is an update: the files moved to a
        // newer version. It records that version and keeps the switch where
        // the merchant left it, so updating never turns a disabled
        // extension back on.
        $existing = Extension::where('name', $name)->first();

        Extension::updateOrCreate(
            ['name' => $name],
            [
                'version' => $manifest['version'] ?? '0.0.0',
                'enabled' => $existing ? (bool) $existing->enabled : true,
                'installed_at' => $existing?->installed_at ?? now(),
            ]
        );

        // Let a subsequent bootEnabled() call (e.g. later in the same
        // request/test) pick up the freshly-installed extension instead of
        // short-circuiting on the cached $booted flag.
        $this->booted = false;

        $this->invalidateRegistryCache();
        $this->clearRouteCacheIfNeeded('install', $name);
    }

    public function enable(string $name): void
    {
        $extension = Extension::where('name', $name)->firstOrFail();
        $extension->update(['enabled' => true]);

        $this->booted = false;

        $this->invalidateRegistryCache();
        $this->clearRouteCacheIfNeeded('enable', $name);
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

        $this->invalidateRegistryCache();
        $this->clearRouteCacheIfNeeded('disable', $name);
    }

    /**
     * Rolls back the extension's own migrations, then deletes its registry
     * row. Core never migrates an extension's tables outside this path.
     */
    public function uninstall(string $name): void
    {
        $manifest = $this->discovered()->get($name);

        // A theme leaves after its own extensions; a package whose theme
        // is gone would have no panel, no views and no way back.
        if (! empty($manifest['theme'])) {
            $stillInstalled = $this->discovered()
                ->filter(fn (array $m) => ($m['for_theme'] ?? null) === $manifest['theme'] && $this->state($m['name']) !== 'not_installed')
                ->pluck('title')
                ->all();

            if ($stillInstalled !== []) {
                throw new \RuntimeException(
                    ($manifest['title'] ?? $name) . ' still has extensions installed: ' . implode(', ', $stillInstalled) . '. Remove them first.'
                );
            }
        }

        if ($manifest) {
            // Every migration the package ever ran, whatever batch it ran
            // in. A plain rollback only undoes the store's last batch, so a
            // package migrated over several updates would leave its older
            // tables behind. Reset with a path touches only the files in it.
            $this->runMigrations($manifest['path'], 'migrate:reset');
        }

        Extension::where('name', $name)->delete();
        \App\Support\PackageAssets::remove($name);
        // An extension's scheduled jobs leave with it: their commands are
        // gone, and a row the heartbeat can no longer run is not a setting
        // worth keeping.
        if (\Illuminate\Support\Facades\Schema::hasTable('automations') && \Illuminate\Support\Facades\Schema::hasColumn('automations', 'extension')) {
            \App\Models\Automation::where('extension', $name)->delete();
        }

        $this->invalidateRegistryCache();
        $this->clearRouteCacheIfNeeded('uninstall', $name);
    }

    /**
     * Whether a compiled route cache is currently active. `loadRoutesFrom()`
     * (which every extension provider's boot() calls to register its own
     * routes) silently no-ops when routes are cached — Laravel serves
     * exclusively from the compiled cache file in that mode and never
     * re-requires route files at all. `docker/entrypoint.sh` runs
     * `route:cache` in production, so this is a real prod condition, not a
     * theoretical one: without clearRouteCacheIfNeeded() below, installing
     * or enabling an extension after the cache was built would silently do
     * nothing — its routes 404 until the next deploy rebuilds the cache.
     * Exposed for the Task 2 Modules UI to surface a "routes cache is stale,
     * a redeploy is needed to fully activate this" warning.
     */
    public function routesCacheStale(): bool
    {
        return app()->routesAreCached();
    }

    /**
     * If a route cache is active, clear it so this lifecycle change actually
     * takes effect on the very next request (falling back to live route-file
     * loading) instead of silently no-oping until someone thinks to redeploy.
     * Logged because clearing a production route cache outside a normal
     * deploy is unusual enough to want a paper trail.
     */
    protected function clearRouteCacheIfNeeded(string $action, string $name): void
    {
        if (! app()->routesAreCached()) {
            return;
        }

        Artisan::call('route:clear');

        Log::warning("ExtensionManager: cleared cached routes after {$action}('{$name}') — a compiled route cache would otherwise mask this extension's routes until the next deploy rebuilds it.");
    }

    /**
     * Invalidates the registry() cache. Every lifecycle method that writes
     * to the `extensions` table must call this — it's the only way a change
     * becomes visible to bootEnabled() before the cache's next natural
     * expiry (there isn't one; it's cached forever until invalidated).
     */
    protected function invalidateRegistryCache(): void
    {
        try {
            Cache::forget(self::REGISTRY_CACHE_KEY);
        } catch (\Throwable) {
            // Same reasoning as the read-side try/catch in registry(): a
            // broken cache backend must not turn a routine install/enable/
            // disable/uninstall into a fatal error.
        }
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
