<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\ThemeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * Core carries one version, shown under the admin navigation. Every theme
 * declares a version of its own, the one that ships with core included,
 * because a theme is released and updated on its own.
 */
class AppVersionTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    public function test_the_version_is_a_valid_release_number(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/', (string) config('app.version'));
    }

    public function test_the_admin_navigation_shows_the_version(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('v-slab__version', false)
            ->assertSee('VentaCart v' . config('app.version'));
    }

    /** A theme is released on its own, the bundled one included, so it carries its own number. */
    public function test_the_bundled_theme_carries_its_own_version(): void
    {
        $bundled = ThemeService::bundled();
        $manifest = json_decode(File::get(ThemeService::root($bundled . '/theme.json')), true);

        $this->assertSame($manifest['version'], app(ExtensionManager::class)->discovered()->get(ThemeService::registryName($bundled))['version']);
    }

    public function test_a_theme_without_a_version_is_refused(): void
    {
        $dir = storage_path('framework/testing/theme-unversioned');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        File::put($dir . '/theme.json', json_encode(['name' => 'Unversioned']));

        $problems = ThemeContract::check($dir);
        File::deleteDirectory($dir);

        $this->assertContains('theme.json needs a version.', $problems);
    }
}
