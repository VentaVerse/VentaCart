<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * A theme that declares `"storage": "own"` keeps its look in tables of its
 * own and only reads core. Switching to it changes which theme is live and
 * writes nothing else into core's settings.
 */
class ThemeOwnStorageTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    /** @return array<string, string> every setting as "group.key" => value */
    private function settingsSnapshot(): array
    {
        return Setting::query()->orderBy('group')->orderBy('key')->get()
            ->mapWithKeys(fn (Setting $s) => ["{$s->group}.{$s->key}" => (string) $s->value])
            ->all();
    }

    public function test_switching_to_a_self_storing_theme_writes_no_look_into_core(): void
    {
        app(ExtensionManager::class)->install('theme');
        Setting::updateOrCreate(['group' => 'colors', 'key' => 'primary'], ['value' => '#123456']);
        app(ThemeService::class)->clearCache();
        $this->installSampleTheme(manifest: ['storage' => 'own', 'colors' => ['primary' => '#abcdef']]);
        $themes = app(ThemeService::class);
        $this->assertTrue($themes->keepsOwnStorage($this->sampleTheme));

        $before = $this->settingsSnapshot();
        $themes->activateTheme($this->sampleTheme);

        $this->assertSame($this->sampleTheme, $themes->getActiveTheme());
        $after = $this->settingsSnapshot();
        $changed = array_keys(array_diff_assoc($after, $before) + array_diff_key($before, $after));
        $this->assertSame(['identity.active_theme'], $changed);
        $this->assertSame('#123456', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));

        // And back: the other theme's settings are as it left them.
        $themes->activateTheme(ThemeService::bundled());
        $this->assertSame('#123456', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
    }
}
