<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\ThemeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * Core carries one version, shown under the admin navigation. The default
 * theme ships inside core, so it has no version of its own; a theme sold
 * separately still must declare one.
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

    public function test_the_default_theme_takes_cores_version(): void
    {
        $this->assertArrayNotHasKey('version', json_decode(File::get(resource_path('themes/default/theme.json')), true));
        $this->assertSame([], ThemeContract::check(resource_path('themes/default')));
        $this->assertSame(config('app.version'), app(ExtensionManager::class)->discovered()->get('theme-default')['version']);
    }

    public function test_a_separate_theme_still_needs_a_version(): void
    {
        $dir = storage_path('framework/testing/theme-unversioned');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        File::put($dir . '/theme.json', json_encode(['name' => 'Unversioned']));

        $problems = ThemeContract::check($dir);
        File::deleteDirectory($dir);

        $this->assertContains('theme.json needs a version.', $problems);
    }

    public function test_the_extensions_page_shows_no_version_for_the_default_theme(): void
    {
        // A store installed before the default theme dropped its own number
        // still has it stored; the card must not show it.
        app(ExtensionManager::class)->install('theme-default');
        \App\Models\Extension::where('name', 'theme-default')->update(['version' => '2.0']);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('Default')
            ->assertDontSee(' v2.0', false);
    }
}
