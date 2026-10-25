<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\Setting;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * A theme that declares `"storage": "own"` keeps its look and layouts in
 * tables of its own and only reads core. Switching to it changes which
 * theme is live and writes nothing else into core's looks or layout rows.
 * A theme without the flag keeps its look and layout in core.
 */
class ThemeOwnStorageTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    private function liveStore(): void
    {
        app(ExtensionManager::class)->install('theme');
        Setting::updateOrCreate(['group' => 'colors', 'key' => 'primary'], ['value' => '#123456']);
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_preset'], ['value' => 'merchant-look']);
        LayoutPlacement::create([
            'theme' => ThemeService::THEME, 'page_type' => 'home', 'region' => 'content_top',
            'widget' => 'catalog.brands', 'title' => 'Our brands', 'sort_order' => 0, 'is_enabled' => true,
        ]);
        app(ThemeService::class)->clearCache();
    }

    /** @return array<string, string> every setting as "group.key" => value */
    private function settingsSnapshot(): array
    {
        return Setting::query()->orderBy('group')->orderBy('key')->get()
            ->mapWithKeys(fn (Setting $s) => ["{$s->group}.{$s->key}" => (string) $s->value])
            ->all();
    }

    public function test_switching_to_a_self_storing_theme_writes_no_look_or_layout_into_core(): void
    {
        $this->liveStore();
        $this->installSampleTheme(manifest: ['storage' => 'own', 'colors' => ['primary' => '#abcdef']]);
        $themes = app(ThemeService::class);
        $this->assertTrue($themes->keepsOwnStorage($this->sampleTheme));

        $before = $this->settingsSnapshot();
        $themes->activateTheme($this->sampleTheme);

        $this->assertSame($this->sampleTheme, $themes->getActiveTheme());

        // Only the live theme changed hands; the outgoing core theme's look is
        // kept under its own name so it can come back.
        $after = $this->settingsSnapshot();
        $changed = array_keys(array_diff_assoc($after, $before) + array_diff_key($before, $after));
        $changed = array_values(array_filter($changed, fn (string $key) => ! str_ends_with(strstr($key, '.', true), '__theme_'.ThemeService::THEME)));
        $this->assertSame(['identity.active_theme'], $changed);

        $this->assertFalse(Setting::where('group', 'like', '%__theme_'.$this->sampleTheme)->exists());
        $this->assertSame('#123456', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
        $this->assertFalse(LayoutPlacement::where('theme', $this->sampleTheme)->exists());
        $this->assertFalse(LayoutRow::where('theme', $this->sampleTheme)->exists());
        $this->assertFalse(\App\Models\LayoutColumns::where('theme', $this->sampleTheme)->exists());

        // Back to the core theme: its look and layout are as it left them,
        // and the self-storing theme left nothing behind.
        $themes->activateTheme(ThemeService::THEME);

        $this->assertSame(ThemeService::THEME, $themes->getActiveTheme());
        $this->assertSame('#123456', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
        $this->assertSame('merchant-look', Setting::where('group', 'identity')->where('key', 'active_preset')->value('value'));
        $this->assertFalse(Setting::where('group', 'like', '%__theme_'.$this->sampleTheme)->exists());
        $this->assertSame(1, LayoutPlacement::where('theme', ThemeService::THEME)->count());
        $this->assertSame(0, LayoutPlacement::where('theme', $this->sampleTheme)->count());
    }

    public function test_switching_to_a_theme_without_the_flag_still_keeps_its_look_and_layout_in_core(): void
    {
        $this->liveStore();
        $this->installSampleTheme(manifest: ['colors' => ['primary' => '#abcdef']]);
        $themes = app(ThemeService::class);
        $this->assertFalse($themes->keepsOwnStorage($this->sampleTheme));

        $themes->activateTheme($this->sampleTheme);

        // The outgoing look is stashed, the incoming default look applied.
        $this->assertSame('#123456', Setting::where('group', 'colors__theme_'.ThemeService::THEME)->where('key', 'primary')->value('value'));
        $this->assertSame('#abcdef', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));

        // A theme with no shipped arrangement takes the merchant's rows along.
        $this->assertSame(['Our brands'], LayoutPlacement::where('theme', $this->sampleTheme)->pluck('title')->all());

        // And it keeps its own look when the store comes back to it.
        Setting::where('group', 'colors')->where('key', 'primary')->update(['value' => '#0a0b0c']);
        $themes->activateTheme(ThemeService::THEME);
        $this->assertSame('#123456', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
        $this->assertSame('#0a0b0c', Setting::where('group', 'colors__theme_'.$this->sampleTheme)->where('key', 'primary')->value('value'));
    }
}
