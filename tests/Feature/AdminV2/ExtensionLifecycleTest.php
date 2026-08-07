<?php

namespace Tests\Feature\AdminV2;

use App\Models\Extension;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame('enabled', $this->fixtureManager()->state('demo'));

        Extension::where('name', 'demo')->update(['enabled' => false]);
        $this->assertSame('disabled', $this->fixtureManager()->state('demo'));
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
}
