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

        // Hit banners' literal URL — now mounted under the *configured*
        // admin prefix at extensions/banners (the Part 1 prefix-bug
        // fix) — rather than route('admin.banners.index'): the named route
        // can't be resolved to build a URL until
        // App\Http\Middleware\BootExtensions has actually run once, and
        // that only happens once a request is dispatched.
        $this->actingAs(User::factory()->create())
            ->get('/'.config('admin.prefix', 'admin').'/extensions/banners')
            ->assertOk();

        // A request has now run BannerServiceProvider::boot() via the
        // kernel — proving install-time continuity actually wires the
        // extension up, not just the DB row.
        $this->assertTrue(Route::has('admin.banners.index'));
    }

    public function test_courier_fulfillment_appears_in_discovered(): void
    {
        // Real courier-fulfillment extension.json (not a fixture) — proves the
        // manifest the kernel adoption task authored is well-formed and
        // discoverable, same as the banners real-extension check above.
        $manifest = (new ExtensionManager())->discovered()->get('courier-fulfillment');

        $this->assertNotNull($manifest);
        $this->assertSame('courier-fulfillment', $manifest['name']);
        $this->assertSame('Fulfillment', $manifest['title']);
        $this->assertSame('1.0.0', $manifest['version']);
        $this->assertSame('Extensions\\CourierFulfillment\\CourierFulfillmentServiceProvider', $manifest['provider']);
        $this->assertSame('fulfillment', $manifest['icon']);
        $this->assertSame('courier', $manifest['type']);
    }

    public function test_courier_fulfillment_install_runs_orders_columns_migration_and_uninstall_rolls_back(): void
    {
        // Real courier-fulfillment extension, installed/uninstalled only
        // against this test's RefreshDatabase-managed in-memory sqlite
        // connection — never the live dev DB (per this task's brief). No
        // HTTP request or explicit bootEnabled() call happens in this test,
        // so the provider itself is never booted here — this test only
        // proves the migration side of the lifecycle, matching the scope of
        // the analogous 'demo' fixture test above.
        $manager = new ExtensionManager();

        $columns = [
            'courier_provider',
            'courier_tracking_number',
            'courier_status',
            'courier_meta',
            'courier_booked_at',
        ];

        foreach ($columns as $column) {
            $this->assertFalse(Schema::hasColumn('orders', $column), "Precondition failed: orders.{$column} should not exist yet.");
        }

        $manager->install('courier-fulfillment');

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column));
        }
        $this->assertDatabaseHas('extensions', ['name' => 'courier-fulfillment', 'enabled' => true]);

        $manager->uninstall('courier-fulfillment');

        foreach ($columns as $column) {
            $this->assertFalse(Schema::hasColumn('orders', $column));
        }
        $this->assertDatabaseMissing('extensions', ['name' => 'courier-fulfillment']);
    }

    public function test_courier_fulfillment_disabled_extension_never_registers_fulfillment_route(): void
    {
        // Mirrors test_disabled_extension_never_registers_its_route (demo
        // fixture) but against the real extension and its frozen route name
        // admin.fulfillment.index: a registry row that starts disabled must
        // never have its provider booted at all, so the route can never be
        // registered in the first place. (Retroactively un-registering an
        // already-booted route isn't possible within one process — see
        // ExtensionManager::disable()'s docblock — so this is the provable
        // form of "disable -> route unregistered".)
        Extension::create([
            'name' => 'courier-fulfillment',
            'version' => '1.0.0',
            'enabled' => false,
            'installed_at' => now(),
        ]);

        (new ExtensionManager())->bootEnabled();

        $this->assertFalse(Route::has('admin.fulfillment.index'));

        // And the sidebar entry goes with it, exactly as before the module
        // gained its own 'enabled_setting' gate: the two mechanisms are
        // independent, and the kernel's is still the one that removes the
        // ROUTE (the setting only hides the entry, leaving its page live).
        $keys = collect(\App\Support\AdminNav::groups(null))
            ->flatMap(fn (array $group) => collect($group['items'])->pluck('key'))
            ->all();
        $this->assertNotContains('fulfillment', $keys);
        $this->assertContains('orders', $keys);
    }

    /**
     * Task 2 (fulfillment-polish) carried finding from Task 1's review:
     * ModuleController's "Configure" link used to always derive
     * "admin.{name}.index", which never matches courier-fulfillment's
     * frozen queue route name (admin.fulfillment.index lives at Sales, not
     * Settings > Modules) — the card would silently show no Configure link
     * at all. extension.json now carries an explicit "configure_route"
     * (admin.fulfillment.settings — Configure means the settings page, not
     * the operational queue) that ModuleController::configureUrl() prefers
     * over the bare-convention fallback. Installed via the real
     * courier-fulfillment extension (not a fixture) so this proves the
     * actual shipped manifest's configure_route resolves correctly.
     */
    public function test_modules_page_configure_link_uses_manifests_configure_route_for_courier_fulfillment(): void
    {
        $admin = User::factory()->create();
        (new ExtensionManager())->install('courier-fulfillment');

        $response = $this->actingAs($admin)->get(route('admin.extensions.index'));

        $response->assertOk();

        // The Configure link (rendered per-card) must be the manifest's
        // configure_route — NOT the "admin.{name}.index" fallback (the
        // queue route), which was the old, wrong derivation this task's
        // carried finding fixes. Scoped to the courier-fulfillment card's
        // own HTML: the sidebar (rendered earlier in the document, in
        // <aside>, well before <main>'s extension cards) legitimately links
        // to the queue route too, on every admin page including this one —
        // a page-wide assertDontSee() would false-positive on that.
        $html = $response->getContent();
        // Scope past the sidebar: 'Fulfillment' now names the module, so
        // its first occurrence is the Sales nav entry, not the card.
        $mainPos = strpos($html, '<main');
        $titlePos = strpos($html, 'Fulfillment', $mainPos ?: 0);
        $this->assertNotFalse($titlePos, 'Fulfillment card title not found on the Extensions page.');
        $card = substr($html, $titlePos, 1500);

        $this->assertStringContainsString(route('admin.fulfillment.settings'), $card);
        $this->assertStringNotContainsString(route('admin.fulfillment.index'), $card);
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
        // The theme continuity migration ships one row on every fresh
        // database; clear it so this test still starts from the empty
        // snapshot its cache choreography is built around.
        Extension::query()->delete();
        \Illuminate\Support\Facades\Cache::forget(ExtensionManager::REGISTRY_CACHE_KEY);

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

    // ------------------------------------------------------------------
    // Task 2: Modules page lifecycle UI (HTTP round trips)
    //
    // All of these run against the 'demo' fixture, never the real
    // 'banners' extension — per this task's absolute rule, banners must
    // never be installed/disabled/uninstalled in a test (even against the
    // in-memory sqlite test DB), to avoid the exact same process-wide
    // spl_autoload_register contamination documented in task-1-report.md's
    // "Incidental fix" section. config('extensions.path') is repointed to
    // the fixtures directory in every test below, before the first HTTP
    // request — see fixtureManager()'s docblock context: ExtensionManager
    // reads that config lazily, on its first discovered() call, which for
    // a fresh request happens inside App\Http\Middleware\BootExtensions,
    // so the override must land before $this->post()/get() is called.
    // ------------------------------------------------------------------

    public function test_http_install_makes_extension_enabled_and_visible_on_modules_page(): void
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.extensions.install', 'demo'));

        $response->assertRedirect(route('admin.extensions.index'));
        $response->assertSessionHas('success');

        $manager = app(ExtensionManager::class);
        $this->assertSame('enabled', $manager->state('demo'));
        $this->assertDatabaseHas('extensions', ['name' => 'demo', 'enabled' => true]);

        $this->actingAs($admin)
            ->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('Demo Extension')
            ->assertSee('Enabled');
    }

    public function test_http_disable_flips_state_and_ui_reflects_it(): void
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);
        $admin = User::factory()->create();

        $manager = app(ExtensionManager::class);
        $manager->install('demo');

        $response = $this->actingAs($admin)->post(route('admin.extensions.disable', 'demo'));

        $response->assertRedirect(route('admin.extensions.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('extensions', ['name' => 'demo', 'enabled' => false]);
        $this->assertSame('disabled', $manager->state('demo'));

        $this->actingAs($admin)
            ->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('Demo Extension')
            ->assertSee('Disabled');

        // Documents a known, already-accepted constraint rather than
        // ignoring it (see ExtensionManager::disable()'s own docblock): the
        // install() call above reset $booted = false, so the very next
        // request — the disable POST itself, since BootExtensions runs
        // before the controller flips the DB row — booted the demo
        // provider and registered its route. Routes cannot be
        // retroactively un-registered from an already-booted process; that
        // only happens on a genuinely fresh PHP process (real deployments)
        // or a fresh Application instance. The provable claim — "a
        // *disabled-from-the-start* extension's provider never boots at
        // all" — is covered separately below,
        // test_disabled_extension_never_registers_its_route(), which is
        // achievable within one process because no prior boot ever ran.
        $this->assertTrue(
            Route::has('extensions.demo.probe'),
            'Expected (see comment): a route booted earlier in this same process cannot be retroactively removed by disable().'
        );
    }

    public function test_disabled_extension_never_registers_its_route(): void
    {
        Extension::create(['name' => 'demo', 'version' => '1.0.0', 'enabled' => false, 'installed_at' => now()]);

        $this->fixtureManager()->bootEnabled();

        $this->assertFalse(
            Route::has('extensions.demo.probe'),
            'A disabled extension\'s provider must never boot, so its routes must never be registered in the first place.'
        );
    }

    public function test_http_uninstall_removes_extension_row_and_card(): void
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);
        $admin = User::factory()->create();

        $manager = app(ExtensionManager::class);
        $manager->install('demo');
        $this->assertTrue(Schema::hasTable('demo_extension_items'));

        $response = $this->actingAs($admin)->post(route('admin.extensions.uninstall', 'demo'));

        $response->assertRedirect(route('admin.extensions.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('extensions', ['name' => 'demo']);
        $this->assertFalse(Schema::hasTable('demo_extension_items'));
        $this->assertSame('not_installed', $manager->state('demo'));

        $this->actingAs($admin)
            ->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('Not installed');
    }

    public function test_lifecycle_actions_404_for_unknown_extension_name_not_a_db_lookup(): void
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);
        $admin = User::factory()->create();

        // A row that exists in the DB but has no filesystem manifest
        // ("missing_files") must still 404 — proving the guard checks
        // discovered() (the filesystem), not whether the name is known to
        // the database.
        Extension::create(['name' => 'ghost', 'version' => '1.0.0', 'enabled' => true, 'installed_at' => now()]);

        foreach (['install', 'enable', 'disable', 'uninstall'] as $action) {
            $this->actingAs($admin)
                ->post(route("admin.extensions.{$action}", 'ghost'))
                ->assertNotFound();

            $this->actingAs($admin)
                ->post(route("admin.extensions.{$action}", 'totally-bogus-extension'))
                ->assertNotFound();
        }

        // Confirms the 404 happened before any write was attempted.
        $this->assertDatabaseHas('extensions', ['name' => 'ghost', 'enabled' => true]);
        $this->assertDatabaseMissing('extensions', ['name' => 'totally-bogus-extension']);
    }

    public function test_invalid_state_transition_is_rejected_with_flash_not_a_crash(): void
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);
        $admin = User::factory()->create();

        // 'demo' is a valid, discovered extension, but has never been
        // installed — enabling it is a nonsensical transition the UI never
        // offers a button for. The controller must reject it gracefully
        // (redirect + flashed error), not 500 or silently no-op.
        $response = $this->actingAs($admin)->post(route('admin.extensions.enable', 'demo'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('extensions', ['name' => 'demo']);
    }

    public function test_install_flashes_note_when_route_cache_was_stale(): void
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);
        $admin = User::factory()->create();

        $this->writeFakeRouteCacheFile();

        try {
            $response = $this->actingAs($admin)->post(route('admin.extensions.install', 'demo'));

            $response->assertRedirect(route('admin.extensions.index'));
            $response->assertSessionHas('success', function ($message) {
                return str_contains($message, 'route cache');
            });
        } finally {
            $this->removeFakeRouteCacheFile();
        }
    }

    public function test_install_does_not_flash_cache_note_when_cache_was_not_stale(): void
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);
        $admin = User::factory()->create();

        $this->assertFalse(app()->routesAreCached(), 'Precondition: no route cache should be active before this test.');

        $response = $this->actingAs($admin)->post(route('admin.extensions.install', 'demo'));

        $response->assertSessionHas('success', function ($message) {
            return ! str_contains($message, 'route cache');
        });
    }
}
