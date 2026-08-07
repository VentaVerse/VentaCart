<?php

namespace Tests\Feature\AdminV2;

use App\Models\Extension;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExtensionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function fixtureManager(): ExtensionManager
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);

        return new ExtensionManager();
    }

    public function test_discovery_reads_manifest(): void
    {
        $manifest = $this->fixtureManager()->discovered()->get('demo');

        $this->assertNotNull($manifest);
        $this->assertSame('Demo Extension', $manifest['title']);
        $this->assertSame('1.0.0', $manifest['version']);
        $this->assertSame('Tests\\Fixtures\\Extensions\\Demo\\DemoServiceProvider', $manifest['provider']);
        $this->assertSame(base_path('tests/fixtures/extensions/demo'), $manifest['path']);
    }

    public function test_state_reports_not_installed_when_no_registry_row(): void
    {
        $this->assertSame('not_installed', $this->fixtureManager()->state('demo'));
    }

    public function test_state_reports_missing_files_when_row_has_no_manifest(): void
    {
        Extension::create([
            'name' => 'ghost',
            'version' => '1.0.0',
            'enabled' => true,
            'installed_at' => now(),
        ]);

        $this->assertSame('missing_files', $this->fixtureManager()->state('ghost'));
    }

    public function test_state_reports_needs_migration_when_manifest_outpaces_registry(): void
    {
        Extension::create([
            'name' => 'demo',
            'version' => '0.9.0',
            'enabled' => true,
            'installed_at' => now(),
        ]);

        $this->assertSame('needs_migration', $this->fixtureManager()->state('demo'));
    }

    public function test_state_reports_enabled_and_disabled(): void
    {
        Extension::create(['name' => 'demo', 'version' => '1.0.0', 'enabled' => true, 'installed_at' => now()]);
        $manager = $this->fixtureManager();
        $this->assertSame('enabled', $manager->state('demo'));

        // Mutate through the manager's own disable(), not a raw Eloquent
        // update: registry() is now cached (see MEDIUM fix in
        // task-1-report.md), and only ExtensionManager's own lifecycle
        // methods invalidate that cache. A raw DB write here would (rightly)
        // still read back the stale cached 'enabled' state.
        $manager->disable('demo');
        $this->assertSame('disabled', $manager->state('demo'));
    }

    public function test_boot_skips_disabled(): void
    {
        Extension::create([
            'name' => 'demo',
            'version' => '1.0.0',
            'enabled' => false,
            'installed_at' => now(),
        ]);

        $this->fixtureManager()->bootEnabled();

        $this->assertFalse(app()->bound('extensions.demo.booted'));
    }

    public function test_boot_registers_enabled_extension_provider(): void
    {
        Extension::create([
            'name' => 'demo',
            'version' => '1.0.0',
            'enabled' => true,
            'installed_at' => now(),
        ]);

        $this->fixtureManager()->bootEnabled();

        $this->assertTrue(app()->bound('extensions.demo.booted'));
        $this->assertTrue(app('extensions.demo.booted'));
    }

    public function test_boot_never_crashes_when_extensions_table_missing(): void
    {
        Schema::drop('extensions');

        $manager = $this->fixtureManager();

        // Must not throw, and registry() must stay empty-safe.
        $manager->bootEnabled();

        $this->assertTrue($manager->registry()->isEmpty());
    }

    public function test_install_uninstall_runs_and_rolls_back_migrations(): void
    {
        $manager = $this->fixtureManager();

        $this->assertFalse(Schema::hasTable('demo_extension_items'));

        $manager->install('demo');

        $this->assertTrue(Schema::hasTable('demo_extension_items'));
        $this->assertDatabaseHas('extensions', [
            'name' => 'demo',
            'version' => '1.0.0',
            'enabled' => true,
        ]);

        $manager->uninstall('demo');

        $this->assertFalse(Schema::hasTable('demo_extension_items'));
        $this->assertDatabaseMissing('extensions', ['name' => 'demo']);
    }

    public function test_banners_migrates_onto_kernel_and_serves_after_install(): void
    {
        // Real banners extension (extensions/banners/extension.json), not the
        // demo fixture — proves the generic kernel actually carries the
        // first real tenant end-to-end: discover -> install -> boot -> serve.
        $manager = new ExtensionManager();

        $this->assertSame('not_installed', $manager->state('banners'));

        $manager->install('banners');

        $this->assertSame('enabled', $manager->state('banners'));
        $this->assertDatabaseHas('extensions', ['name' => 'banners', 'enabled' => true]);

        // Hit banners' literal URL (still hardcoded 'admin/banners' — that
        // prefix bug is Part 1's fix, out of scope here) rather than
        // route('admin.banners.index'): the named route can't be resolved to
        // build a URL until App\Http\Middleware\BootExtensions has actually
        // run once, and that only happens once a request is dispatched.
        $this->actingAs(User::factory()->create())
            ->get('/admin/banners')
            ->assertOk();

        // A request has now run BannerServiceProvider::boot() via the
        // kernel — proving install-time continuity actually wires the
        // extension up, not just the DB row.
        $this->assertTrue(Route::has('admin.banners.index'));
    }

    public function test_create_extensions_table_migration_seeds_banners_continuity_row_only_when_legacy_tables_exist(): void
    {
        // Simulates an EXISTING deploy where banners' own migrations already
        // ran under the old (pre-kernel) unconditional wiring — its tables
        // exist, so the create_extensions_table migration must seed the
        // continuity row. This is a decision the migration makes at the
        // moment it runs, so we replay it against a DB state that already
        // has those legacy tables (mirroring a real upgrade), not against
        // this test class's already-migrated schema.
        Schema::dropIfExists('extensions');
        Schema::create('banner_groups', function ($table) {
            $table->id();
        });

        (require base_path('database/migrations/2026_08_07_172038_create_extensions_table.php'))->up();

        $this->assertDatabaseHas('extensions', ['name' => 'banners', 'enabled' => true]);
    }

    public function test_create_extensions_table_migration_skips_banners_seed_on_fresh_install(): void
    {
        // No banner_groups/banners tables present — a genuinely fresh
        // install must NOT claim banners is already installed.
        Schema::dropIfExists('extensions');

        (require base_path('database/migrations/2026_08_07_172038_create_extensions_table.php'))->up();

        $this->assertDatabaseMissing('extensions', ['name' => 'banners']);
    }

    public function test_routes_cache_stale_reports_current_cache_state(): void
    {
        $this->assertFalse(app()->routesAreCached(), 'Precondition: no route cache should be active before this test.');

        $manager = $this->fixtureManager();
        $this->assertFalse($manager->routesCacheStale());

        $this->writeFakeRouteCacheFile();

        try {
            $this->assertTrue($manager->routesCacheStale());
        } finally {
            $this->removeFakeRouteCacheFile();
        }
    }

    public function test_install_clears_a_present_route_cache(): void
    {
        $this->assertFalse(app()->routesAreCached(), 'Precondition: no route cache should be active before this test.');

        $this->writeFakeRouteCacheFile();
        $this->assertTrue(app()->routesAreCached(), 'Precondition: a route cache file must be present before this test.');

        try {
            $manager = $this->fixtureManager();
            $manager->install('demo');

            // install()'s clearRouteCacheIfNeeded() already ran route:clear
            // internally; forget the memoized binding once more (see
            // forgetRoutesCachedMemoization()'s docblock) so this assertion
            // observes the real (now-absent) file rather than whatever was
            // memoized before install() ran.
            $this->forgetRoutesCachedMemoization();

            $this->assertFalse(
                app()->routesAreCached(),
                "install() must clear a present route cache — loadRoutesFrom() (called by every extension provider's boot()) silently no-ops when routes are cached, so a freshly-installed extension's routes would otherwise 404 until the next deploy rebuilds the cache."
            );
        } finally {
            // Belt and suspenders: install() should have already cleared it,
            // but if the assertion above failed, make sure this test doesn't
            // leave a stale cache file behind for every test/request after it.
            $this->removeFakeRouteCacheFile();
        }
    }

    /**
     * Writes a fake route cache file directly rather than running the real
     * `route:cache` artisan command: that command boots a second, fully
     * independent Application internally (RouteCacheCommand::
     * getFreshApplication() -> `require bootstrap/app.php` again) to get a
     * "clean" route list, and doing that mid-test corrupts the current
     * test's already-migrated in-memory SQLite connection (confirmed
     * empirically — it left later `extensions` table reads in this same
     * test 500ing with "no such table"). app()->routesAreCached() only
     * checks file existence, not content, so a stub file is sufficient to
     * exercise the real code path (App\Http\Middleware and
     * ExtensionManager only ever check presence, never read the file
     * themselves — Laravel's own route-loading bootstrapper does that, and
     * we don't exercise that path here).
     */
    private function writeFakeRouteCacheFile(): void
    {
        file_put_contents(app()->getCachedRoutesPath(), "<?php\n\nreturn static function () {};\n");
        $this->forgetRoutesCachedMemoization();
    }

    private function removeFakeRouteCacheFile(): void
    {
        $path = app()->getCachedRoutesPath();

        if (is_file($path)) {
            unlink($path);
        }

        $this->forgetRoutesCachedMemoization();
    }

    /**
     * Illuminate\Foundation\Application::routesAreCached() memoizes its
     * result the first time anything checks it (container binding
     * 'routes.cached'), which every extension provider's boot() already
     * does via loadRoutesFrom(). That's harmless in production — each
     * request is a fresh Application, so the memoized value always matches
     * that request's actual file state — but within one long-lived test
     * Application it means a check made before the cache file is written or
     * removed permanently shadows the real file state for the rest of the
     * test. forgetInstance() is the supported way to reset a container
     * binding; this makes routesAreCached() re-evaluate the real file
     * again, exactly as if this were a fresh request.
     */
    private function forgetRoutesCachedMemoization(): void
    {
        app()->forgetInstance('routes.cached');
    }

    public function test_registry_is_cached_across_manager_instances_and_invalidated_by_install(): void
    {
        // Two independent manager instances sharing the same underlying
        // (config('cache.default')) cache store — this is what actually
        // happens across two separate HTTP requests/processes in production,
        // unlike the in-process $booted flag which only helps a single
        // long-lived instance (Octane).
        $first = $this->fixtureManager();
        $this->assertTrue($first->registry()->isEmpty());

        Extension::create(['name' => 'demo', 'version' => '1.0.0', 'enabled' => true, 'installed_at' => now()]);

        // A second manager, reading through the SAME cache store, must still
        // see the pre-mutation empty snapshot — proving the cache (not just
        // the $booted per-instance flag) is what's doing the work here.
        $second = $this->fixtureManager();
        $this->assertTrue($second->registry()->isEmpty(), 'registry() should still be serving the cached empty snapshot.');

        // Going through install() (not a raw Eloquent write) is what's
        // supposed to invalidate the cache.
        $second->install('demo');

        $third = $this->fixtureManager();
        $this->assertTrue($third->registry()->has('demo'), 'install() must invalidate the registry cache so the next read sees the new row.');
    }
}
