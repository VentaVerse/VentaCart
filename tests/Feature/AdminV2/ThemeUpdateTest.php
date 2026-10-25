<?php

namespace Tests\Feature\AdminV2;

use App\Models\Extension;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * A theme whose files are newer than its installed version keeps drawing
 * the storefront until the merchant runs the update from the admin. Only a
 * theme whose new files break the contract stops being served.
 */
class ThemeUpdateTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    private function admin(): void
    {
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    /** Copy newer files over the installed sample theme. */
    private function bumpSampleVersion(string $version): void
    {
        $json = resource_path('themes/' . $this->sampleTheme . '/theme.json');
        $manifest = json_decode((string) file_get_contents($json), true);
        $manifest['version'] = $version;
        file_put_contents($json, json_encode($manifest, JSON_PRETTY_PRINT));

        app()->forgetInstance(ExtensionManager::class);
    }

    public function test_the_storefront_keeps_the_installed_theme_while_its_update_waits(): void
    {
        $this->installSampleTheme(activate: true);
        $this->bumpSampleVersion('9.9.9');

        $this->assertSame('needs_migration', app(ExtensionManager::class)->state('theme-sample'));
        $this->assertTrue(app(ThemeService::class)->hasActiveTheme());

        $this->get('/')->assertOk()->assertDontSee('No theme installed');
    }

    public function test_the_admin_shows_the_update_and_running_it_clears_the_flag(): void
    {
        $this->installSampleTheme(activate: true);
        $this->bumpSampleVersion('9.9.9');
        $this->admin();

        $this->get(route('admin.theme.index'))->assertOk()
            ->assertSee('Update available')
            ->assertSee('Version 9.9.9 is on disk')
            ->assertSee('The storefront keeps running the installed theme until you update.');
        $this->get(route('admin.extensions.index'))->assertOk()->assertSee('Update available: v9.9.9');

        $this->post(route('admin.theme.package.install'), ['theme' => 'sample'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Sample is updated to version 9.9.9.');

        $this->assertSame('9.9.9', Extension::where('name', 'theme-sample')->value('version'));
        $this->assertSame('enabled', app(ExtensionManager::class)->state('theme-sample'));
        $this->assertFalse(app(ThemeService::class)->getAvailableThemes()['sample']['update_available']);
        $this->get(route('admin.theme.index'))->assertOk()->assertDontSee('Update available');
        $this->get('/')->assertOk();
    }

    public function test_the_update_runs_the_migrations_the_new_files_bring(): void
    {
        $this->installSampleTheme(activate: true);
        $this->assertFalse(Schema::hasTable('sample_theme_notes'));

        $dir = resource_path('themes/' . $this->sampleTheme . '/database/migrations');
        File::ensureDirectoryExists($dir);
        File::put($dir . '/2026_01_01_000000_create_sample_theme_notes_table.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('sample_theme_notes', function (Blueprint $table) {
                        $table->id();
                    });
                }

                public function down(): void
                {
                    Schema::dropIfExists('sample_theme_notes');
                }
            };
            PHP);
        $this->bumpSampleVersion('9.9.9');
        $this->admin();

        $this->post(route('admin.theme.package.install'), ['theme' => 'sample'])->assertSessionHas('success');

        $this->assertTrue(Schema::hasTable('sample_theme_notes'));
    }

    public function test_updating_keeps_a_switched_off_package_switched_off(): void
    {
        $this->installSampleTheme();
        app(ExtensionManager::class)->disable('theme-sample');
        $this->bumpSampleVersion('9.9.9');

        app(ExtensionManager::class)->install('theme-sample');

        $row = Extension::where('name', 'theme-sample')->first();
        $this->assertSame('9.9.9', $row->version);
        $this->assertFalse((bool) $row->enabled);
    }

    public function test_a_theme_whose_new_files_break_the_contract_is_not_served(): void
    {
        $this->installSampleTheme(activate: true);
        // The new files lost a view every store renders.
        unlink(resource_path('themes/' . $this->sampleTheme . '/views/home.blade.php'));
        $this->bumpSampleVersion('9.9.9');

        $this->assertFalse(app(ThemeService::class)->hasActiveTheme());
        $this->get('/')->assertStatus(503)->assertSee('No theme installed');

        $this->admin();
        $this->get(route('admin.theme.index'))->assertOk()
            ->assertSee('Update available')
            ->assertSee('do not keep the theme contract');
        $this->post(route('admin.theme.package.install'), ['theme' => 'sample'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'View [home] is missing.'));
    }
}
