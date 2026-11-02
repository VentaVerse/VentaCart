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
        $this->get(route('admin.theme.customise', ['theme' => 'sample', 'tab' => 'theme_settings']))->assertRedirect($panel);
        $this->post(route('admin.theme.update-theme-settings'), ['theme' => 'sample', 'theme_settings' => ['glow_intensity' => 'high']])
            ->assertRedirect($panel);
        $this->post(route('admin.theme.settings.update'), ['theme' => 'sample', 'groups' => ['colors' => ['primary' => '#010203']]])
            ->assertRedirect($panel);
        $this->post(route('admin.theme.revert-colors'), ['theme' => 'sample'])->assertRedirect($panel);

        // The live preview edits the live theme's colors: refused too.
        $this->get(route('admin.theme.preview'))->assertRedirect($panel);
        $this->post(route('admin.theme.preview.save'), ['payload' => json_encode(['global' => ['primary' => '#010203']])])
            ->assertRedirect($panel);

        $this->assertSame($before, $this->settingsSnapshot());
    }

    public function test_a_self_storing_theme_without_a_panel_is_refused_and_has_no_core_editor_links(): void
    {
        $this->boot(['storage' => 'own', 'editor' => ''], live: false);
        $before = $this->settingsSnapshot();

        $this->get(route('admin.theme.customise', ['theme' => 'sample']))->assertNotFound();
        $this->post(route('admin.theme.update-theme-settings'), ['theme' => 'sample', 'theme_settings' => ['glow_intensity' => 'high']])
            ->assertNotFound();
        $this->post(route('admin.theme.settings.update'), ['theme' => 'sample', 'groups' => ['colors' => ['primary' => '#010203']]])
            ->assertNotFound();
        $this->assertSame($before, $this->settingsSnapshot());

        // The directory offers core's editors for the core theme only.
        $html = $this->get(route('admin.theme.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('theme=sample', $html);
        $this->assertStringContainsString('Use this theme', $html);

        // Live, it offers no core editor either, and the preview is refused.
        app(ThemeService::class)->activateTheme($this->sampleTheme);
        app(ThemeService::class)->clearCache();
        $html = $this->get(route('admin.theme.index'))->assertOk()->getContent();
        // Venta, not live, still offers its own Customise; the live theme has none.
        $this->assertStringContainsString(route('admin.theme.customise', ['theme' => 'venta']), $html);
        $this->assertStringNotContainsString('href="'.route('admin.theme.customise').'"', $html);
        $this->assertStringNotContainsString(route('admin.theme.preview'), $html);
        $this->get(route('admin.theme.preview'))->assertNotFound();
    }

    public function test_the_default_theme_keeps_its_options_page(): void
    {
        $this->boot([], live: false);

        $this->get(route('admin.theme.customise', ['tab' => 'theme_settings']))->assertOk()
            ->assertSee('<option value="medium"', false)
            ->assertSee('>Medium</option>', false);

        $this->post(route('admin.theme.update-theme-settings'), ['theme_settings' => ['glow_intensity' => 'high']])->assertRedirect();
        $this->assertSame('high', Setting::where('group', 'theme_settings')->where('key', 'venta_glow_intensity')->value('value'));
    }

    public function test_a_labelled_option_shows_its_label_and_stores_its_value(): void
    {
        $this->boot([
            'editor' => '',
            'settings' => ['card_corners' => [
                'type' => 'select', 'label' => 'Card corners', 'default' => 'soft',
                'options' => ['soft' => 'Rounded corners', 'sharp' => 'Square corners'],
            ]],
        ], live: true);

        $this->get(route('admin.theme.customise', ['tab' => 'theme_settings']))->assertOk()
            ->assertSee('<option value="sharp" >Square corners</option>', false)
            ->assertSee('Rounded corners');

        $this->post(route('admin.theme.update-theme-settings'), ['theme_settings' => ['card_corners' => 'sharp']])->assertRedirect();
        $this->assertSame('sharp', Setting::where('group', 'theme_settings')->where('key', 'sample_card_corners')->value('value'));

        // The label is not a value: it is refused and nothing changes.
        $this->post(route('admin.theme.update-theme-settings'), ['theme_settings' => ['card_corners' => 'Rounded corners']])
            ->assertSessionHasErrors('theme_settings.card_corners');
        $this->assertSame('sharp', Setting::where('group', 'theme_settings')->where('key', 'sample_card_corners')->value('value'));
    }
}
