<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * Core's theme editor (Customise, its Theme options, the live preview) edits
 * only themes that keep their settings in core. A theme declaring
 * `"storage": "own"` edits them in its own panel: core shows no way in, and
 * a request made anyway is sent to that panel (or 404) with nothing written.
 */
class ThemeOptionsStorageTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    /** @param  array<string, mixed>  $manifest */
    private function boot(array $manifest, bool $live): void
    {
        $extensions = app(ExtensionManager::class);
        foreach (['theme', 'banners'] as $name) {
            $extensions->install($name);
        }
        $this->installSampleTheme(manifest: $manifest);
        app(ExtensionManager::class)->bootEnabled();
        app(ThemeService::class)->activateTheme($live ? $this->sampleTheme : ThemeService::THEME);
        app(ThemeService::class)->clearCache();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    /** @return array<string, string> every setting as "group.key" => value */
    private function settingsSnapshot(): array
    {
        return Setting::query()->orderBy('group')->orderBy('key')->get()
            ->mapWithKeys(fn (Setting $s) => ["{$s->group}.{$s->key}" => (string) $s->value])
            ->all();
    }

    public function test_a_self_storing_theme_with_a_panel_is_sent_there_and_nothing_is_written(): void
    {
        $this->boot(['storage' => 'own'], live: true);
        $before = $this->settingsSnapshot();

        $panel = route('admin.dashboard');
        $this->get(route('admin.header-footer'))->assertRedirect($panel);
        $this->post(route('admin.header-footer.announcement'), ['announcement_text' => 'x'])->assertRedirect($panel);
        $this->post(route('admin.header-footer.top-bar'), ['topbar_links' => '[]'])->assertRedirect($panel);

        $this->assertSame($before, $this->settingsSnapshot());
    }

    public function test_a_self_storing_theme_without_a_panel_is_refused_and_has_no_core_editor_links(): void
    {
        $this->boot(['storage' => 'own', 'editor' => ''], live: false);

        // The directory offers no core editor for it.
        $html = $this->get(route('admin.theme.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('theme=sample', $html);
        $this->assertStringContainsString('Use this theme', $html);

        // Live, core's Header & footer is refused rather than written for it.
        app(ThemeService::class)->activateTheme($this->sampleTheme);
        app(ThemeService::class)->clearCache();
        $before = $this->settingsSnapshot();

        $this->get(route('admin.header-footer'))->assertNotFound();
        $this->post(route('admin.header-footer.announcement'), ['announcement_text' => 'x'])->assertNotFound();
        $this->assertSame($before, $this->settingsSnapshot());
    }

}
