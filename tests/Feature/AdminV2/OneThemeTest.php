<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Owner ruling 2026-08-20, revised 2026-08-21.
 *
 * The three legacy folders are still gone and still survive as presets: they
 * were one skeleton in three palettes, which is why switching them only ever
 * changed colour. What came back on 2026-08-21 is theme SELECTION, because a
 * theme is a folder of views and CSS again, and a second theme is meant to
 * look nothing like the first.
 *
 * The live hazard this pins down: a store that predates the consolidation still
 * has identity.active_theme = 'stage-ready' in its settings table. If view
 * resolution kept honouring that value, the first deploy after this release
 * would look for views in a directory the release deletes, and the storefront
 * would go white. Not a wrong colour, no storefront. That is the same shape of
 * failure as the order-snapshot outage: correct code, live data left behind.
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
    public function test_a_theme_that_is_not_installed_falls_back_to_the_default(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'crimson-dusk']);
        app(ThemeService::class)->clearCache();

        $this->assertSame(ThemeService::THEME, app(ThemeService::class)->getActiveTheme());
        $this->assertStringEndsWith('themes/' . ThemeService::THEME, app(ThemeService::class)->getThemePath());
    }

    public function test_an_installed_theme_named_in_settings_is_honoured(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => ThemeService::THEME]);
        app(ThemeService::class)->clearCache();

        $this->assertSame(ThemeService::THEME, app(ThemeService::class)->getActiveTheme());
        $this->assertArrayHasKey(ThemeService::THEME, app(ThemeService::class)->getInstalledThemes());
    }

    /** The default theme is always installed; others may join it. */
    public function test_the_default_theme_ships(): void
    {
        $dirs = array_map('basename', File::directories(resource_path('themes')));

        $this->assertContains(ThemeService::THEME, $dirs);
        $this->assertNotContains('stage-ready', $dirs);
    }

}
