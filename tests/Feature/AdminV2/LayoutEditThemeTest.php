<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * Editing is not tied to the live theme: the core Layout editor takes
 * ?theme={slug} for any installed theme that rides it; a theme that
 * brings its own editor is never edited here.
 */
class LayoutEditThemeTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    private function boot(string $activate = 'sample'): void
    {
        $m = app(ExtensionManager::class);
        foreach (['theme', 'banners'] as $name) {
            $m->install($name);
        }
        $this->installSampleTheme();
        app(ExtensionManager::class)->bootEnabled();
        app(ThemeService::class)->activateTheme($activate);
        app(ThemeService::class)->clearCache();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    private function payload(string $text): array
    {
        return ['page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0], 'settings' => [], 'rows' => [
            ['id' => null, 'region' => 'content_top', 'columns' => [8], 'style' => [], 'responsive' => [], 'placements' => [
                ['widget' => 'content.text', 'column' => 0, 'config' => ['content' => $text], 'enabled' => true],
            ]],
        ]];
    }

    public function test_the_core_layout_editor_edits_the_themes_that_ride_it(): void
    {
        $this->boot('sample');

        // Sample brings its own editor, so with Sample live the core editor
        // opens on the first theme that rides it: Venta.
        $html = $this->get(route('admin.layout'))->assertOk()->getContent();
        $this->assertStringContainsString('theme=venta', $html);
        $this->assertStringContainsString('Venta', $html);

        $this->post(route('admin.layout.update', ['theme' => 'venta']), ['payload' => json_encode($this->payload('VENTA-OWN-ROW'))])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, LayoutPlacement::where('theme', 'venta')->where('widget', 'content.text')->count());
        $this->assertSame(0, LayoutPlacement::where('theme', 'sample')->where('widget', 'content.text')->count());

        // Sample's storefront does not wear it; Venta's does once it is live.
        $this->assertStringNotContainsString('VENTA-OWN-ROW', $this->get('/')->assertOk()->getContent());
        app(ThemeService::class)->activateTheme('venta');
        app(ThemeService::class)->clearCache();
        $this->assertStringContainsString('VENTA-OWN-ROW', $this->get('/')->assertOk()->getContent());

        // With Venta live (a riding theme), no parameter edits Venta itself.
        $this->post(route('admin.layout.update'), ['payload' => json_encode($this->payload('LIVE-ROW'))])->assertRedirect();
        $this->assertSame(1, LayoutPlacement::where('theme', 'venta')->where('page_type', 'home')->whereJsonContains('config->content', 'LIVE-ROW')->count());
    }

    public function test_a_decoupled_or_unknown_theme_parameter_lands_on_a_riding_theme(): void
    {
        $this->boot('sample');
        // Sample is decoupled: asking the core editor for it, or for nothing
        // real, both land on the theme that rides the editor.
        $this->post(route('admin.layout.update', ['theme' => 'sample']), ['payload' => json_encode($this->payload('SOMEWHERE'))])->assertRedirect();
        $this->post(route('admin.layout.update', ['theme' => 'nope']), ['payload' => json_encode($this->payload('ELSEWHERE'))])->assertRedirect();
        // Each save is the whole page, so the second replaced the first;
        // both landed on Venta and neither touched Sample.
        $this->assertSame(1, LayoutPlacement::where('theme', 'venta')->whereJsonContains('config->content', 'ELSEWHERE')->count());
        $this->assertSame(0, LayoutPlacement::where('theme', 'sample')->where('widget', 'content.text')->count());
        $this->assertSame(0, LayoutPlacement::where('theme', 'nope')->count());
    }
}
