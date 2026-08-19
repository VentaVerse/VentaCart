<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Owner ruling, 2026-08-20: the legacy theme switcher is retired. Venta has one
 * theme, and every look is a preset over it. stage-ready, clean-light and
 * crimson-dusk are no longer folders a store can be pointed at.
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

    public function test_the_theme_is_fixed_and_no_longer_read_from_settings(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'crimson-dusk']);
        app(ThemeService::class)->clearCache();

        $this->assertSame(ThemeService::THEME, app(ThemeService::class)->getActiveTheme());
        $this->assertStringEndsWith('themes/' . ThemeService::THEME, app(ThemeService::class)->getThemePath());
    }

    /** The looks survive the folders: each retired theme is still a preset. */
    public function test_every_retired_theme_is_still_available_as_a_preset(): void
    {
        $presets = app(ThemeService::class)->getInstalledPresets();

        foreach (['stage-ready', 'clean-light', 'crimson-dusk'] as $slug) {
            $this->assertArrayHasKey($slug, $presets, "The {$slug} look was lost with its folder.");
        }
    }

    public function test_only_one_theme_folder_ships(): void
    {
        $dirs = array_map('basename', File::directories(resource_path('themes')));

        $this->assertSame([ThemeService::THEME], $dirs);
    }

    /**
     * The one-off that carries an existing store across. It is the only thing
     * that still needs identity.active_theme, because it is the only record of
     * what the store used to run: it maps that to the matching preset and
     * rewrites theme_settings keys from the old slug prefix to the master's.
     *
     * Without this run, a live store keeps its colours (one shared group) but
     * loses its theme options, because the master reads venta_* and the store
     * still holds stage-ready_*.
     */
    public function test_the_one_off_carries_a_legacy_store_onto_the_master(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'stage-ready']);
        Setting::updateOrCreate(['group' => 'theme_settings', 'key' => 'stage-ready_show_brands'], ['value' => '1']);
        app(ThemeService::class)->clearCache();

        $this->artisan('venta:adopt-master-theme')->assertSuccessful();

        $this->assertSame('stage-ready',
            Setting::where('group', 'identity')->where('key', 'active_preset')->value('value'),
            'The look the store was running was not preserved as its preset.');

        $this->assertSame('1',
            Setting::where('group', 'theme_settings')->where('key', 'venta_show_brands')->value('value'),
            'A theme option was dropped instead of being remapped onto the master.');

        $this->assertNull(
            Setting::where('group', 'theme_settings')->where('key', 'stage-ready_show_brands')->value('value'),
            'The legacy key was left behind, so the option now exists twice.');
    }
}
