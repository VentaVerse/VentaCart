<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing is not tied to the live theme: the core Layout editor takes
 * ?theme={slug} for any installed theme, and Plaza's panel edits Plaza's
 * own layout and look even while another theme is live.
 */
class LayoutEditThemeTest extends TestCase
{
    use RefreshDatabase;

    private function boot(string $activate = 'plaza'): void
    {
        $m = app(ExtensionManager::class);
        foreach (['theme', 'banners', ThemeService::registryName('plaza')] as $name) {
            $m->install($name);
        }
        $m->bootEnabled();
        app(ThemeService::class)->activateTheme($activate);
        app(ThemeService::class)->clearCache();
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
        $this->boot('plaza');

        // Plaza brings its own editor, so with Plaza live the core editor
        // opens on the first theme that rides it: Venta.
        $html = $this->get(route('admin.layout'))->assertOk()->getContent();
        $this->assertStringContainsString('theme=venta', $html);
        $this->assertStringContainsString('Venta', $html);

        $this->post(route('admin.layout.update', ['theme' => 'venta']), ['payload' => json_encode($this->payload('VENTA-OWN-ROW'))])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, LayoutPlacement::where('theme', 'venta')->where('widget', 'content.text')->count());
        $this->assertSame(0, LayoutPlacement::where('theme', 'plaza')->where('widget', 'content.text')->count());

        // Plaza's storefront does not wear it; Venta's does once it is live.
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
        $this->boot('plaza');
        // Plaza is decoupled: asking the core editor for it, or for nothing
        // real, both land on the theme that rides the editor.
        $this->post(route('admin.layout.update', ['theme' => 'plaza']), ['payload' => json_encode($this->payload('SOMEWHERE'))])->assertRedirect();
        $this->post(route('admin.layout.update', ['theme' => 'nope']), ['payload' => json_encode($this->payload('ELSEWHERE'))])->assertRedirect();
        // Each save is the whole page, so the second replaced the first;
        // both landed on Venta and neither touched Plaza.
        $this->assertSame(1, LayoutPlacement::where('theme', 'venta')->whereJsonContains('config->content', 'ELSEWHERE')->count());
        $this->assertSame(0, LayoutPlacement::where('theme', 'plaza')->where('widget', 'content.text')->count());
        $this->assertSame(0, LayoutPlacement::where('theme', 'nope')->count());
    }

    public function test_plazas_panel_edits_plaza_while_another_theme_is_live(): void
    {
        // Activate plaza (its look becomes live), then venta: plaza's look is stashed.
        $this->boot('plaza');
        app(ThemeService::class)->activateTheme('venta');
        app(ThemeService::class)->clearCache();
        $ventaPrimary = (string) Setting::where('group', 'colors')->where('key', 'primary')->value('value');

        // The panel answers, reads Plaza's stashed look, and its layout scope is Plaza.
        $html = $this->get(route('admin.plaza.panel', ['panel' => 'builder', 'page' => 'home']))->assertOk()->getContent();
        preg_match('/id="pp-config"[^>]*>(.*?)<\/script>/s', $html, $m);
        $config = json_decode(html_entity_decode($m[1]), true);
        $stashPrimary = (string) Setting::where('group', 'colors__theme_plaza')->where('key', 'primary')->value('value');
        $this->assertSame($stashPrimary, $config['colors']['primary']);

        // A save from the panel writes Plaza's rows and Plaza's stash, and
        // leaves the live Venta look untouched.
        $this->postJson(route('admin.plaza.editor.save'), ['page' => 'home', 'layout' => ['columns' => ['left' => 0, 'right' => 0], 'settings' => [], 'rows' => ['content_top' => [['id' => null, 'columns' => [8], 'enabled' => true, 'style' => [], 'cols' => [[['widget' => 'content.text', 'config' => ['content' => 'PLAZA-WHILE-VENTA'], 'enabled' => true]]]]]]], 'colors' => ['primary' => '#123456'], 'fonts' => [], 'theme_settings' => []])->assertOk();

        $this->assertSame(1, LayoutPlacement::where('theme', 'plaza')->whereJsonContains('config->content', 'PLAZA-WHILE-VENTA')->count());
        $this->assertSame(0, LayoutPlacement::where('theme', 'venta')->whereJsonContains('config->content', 'PLAZA-WHILE-VENTA')->count());
        $this->assertSame('#123456', Setting::where('group', 'colors__theme_plaza')->where('key', 'primary')->value('value'));
        $this->assertSame($ventaPrimary, (string) Setting::where('group', 'colors')->where('key', 'primary')->value('value'));

        // Venta's storefront never shows it; switching back to Plaza does,
        // wearing the stashed edit.
        $this->assertStringNotContainsString('PLAZA-WHILE-VENTA', $this->get('/')->assertOk()->getContent());
        app(ThemeService::class)->activateTheme('plaza');
        app(ThemeService::class)->clearCache();
        $this->assertStringContainsString('PLAZA-WHILE-VENTA', $this->get('/')->assertOk()->getContent());
        $this->assertSame('#123456', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
    }
}
