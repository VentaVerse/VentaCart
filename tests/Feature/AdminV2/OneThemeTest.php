<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A store keeps rendering whatever its settings say about the theme.
 *
 * A store can still name a theme whose folder a release removed. If view
 * resolution honoured that value, the storefront would look for views in a
 * folder that no longer exists and go blank, so a missing or uninstalled
 * theme falls back to the bundled one.
 */
class OneThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_store_still_pointed_at_a_deleted_theme_keeps_rendering(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'stage-ready']);
        app(ThemeService::class)->clearCache();

        $this->get('/')->assertOk();
    }

    /** A setting naming a theme that is not installed cannot take the shop down. */
    public function test_a_theme_that_is_not_installed_falls_back_to_the_bundled_theme(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'crimson-dusk']);
        app(ThemeService::class)->clearCache();

        $this->assertSame(ThemeService::bundled(), app(ThemeService::class)->getActiveTheme());
        $this->assertStringEndsWith('themes/' . ThemeService::bundled(), app(ThemeService::class)->getThemePath());
    }

    public function test_an_installed_theme_named_in_settings_is_honoured(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => ThemeService::bundled()]);
        app(ThemeService::class)->clearCache();

        $this->assertSame(ThemeService::bundled(), app(ThemeService::class)->getActiveTheme());
        $this->assertArrayHasKey(ThemeService::bundled(), app(ThemeService::class)->getInstalledThemes());
    }

    /** The bundled theme ships; others may join it. */
    public function test_the_bundled_theme_ships(): void
    {
        $dirs = array_map('basename', File::directories(resource_path('themes')));

        $this->assertContains(ThemeService::bundled(), $dirs);
        $this->assertNotContains('stage-ready', $dirs);
    }

}
