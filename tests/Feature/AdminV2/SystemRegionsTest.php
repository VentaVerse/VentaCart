<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\User;
use App\Services\ThemeService;
use App\Support\Regions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Placeable system widgets (Plaza plan, phase 6): a theme declares a region
 * whose contents default to its own pieces; the storefront shows those
 * pieces until the merchant arranges the region, and the editor offers
 * them as a row the merchant takes over by saving.
 */
class SystemRegionsTest extends TestCase
{
    use RefreshDatabase;

    private function usePlaza(): void
    {
        app(ThemeService::class)->activateTheme('plaza');
        app(ThemeService::class)->clearCache();
    }

    public function test_plaza_declares_a_header_region_with_its_shipped_piece_and_venta_does_not(): void
    {
        $this->assertSame([], Regions::defaults('header', 'venta'));
        $this->assertSame([['widget' => 'system.header', 'title' => null, 'config' => []]], Regions::defaults('header', 'plaza'));
        $this->assertSame('*', Regions::page('header', 'plaza'));
    }

    public function test_the_header_renders_from_the_shipped_piece_until_arranged(): void
    {
        $this->usePlaza();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('site-header', $html);
        $this->assertStringContainsString('nav-bar', $html);
        $this->assertSame(0, LayoutPlacement::where('region', 'header')->count(), 'Nothing was written to show the default.');
    }

    public function test_an_arranged_header_replaces_the_shipped_piece(): void
    {
        $this->usePlaza();
        LayoutPlacement::create(['theme' => 'plaza', 'page_type' => '*', 'region' => 'header', 'widget' => 'system.logo', 'sort_order' => 0, 'is_enabled' => true]);
        LayoutPlacement::create(['theme' => 'plaza', 'page_type' => '*', 'region' => 'header', 'widget' => 'system.nav', 'sort_order' => 1, 'is_enabled' => true]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="logo', $html);
        $this->assertStringContainsString('nav-bar', $html);
        $this->assertStringNotContainsString('search-bar', $html, 'The search piece was not placed, so it is not there.');
    }

    public function test_the_editor_offers_the_shipped_row_and_saving_makes_it_the_merchants(): void
    {
        app(\App\Services\ExtensionManager::class)->install('theme');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->usePlaza();
        $this->actingAs(User::factory()->create());

        $html = $this->get(route('admin.layout', ['page' => '*']))->assertOk()->getContent();
        $this->assertStringContainsString('"shipped":true', str_replace('\\u0022', '"', $html));
        $this->assertStringContainsString('Header (as arranged)', $html);

        $this->post(route('admin.layout.update'), ['payload' => json_encode([
            'page_type' => '*', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => null, 'region' => 'header', 'columns' => [8], 'enabled' => true, 'placements' => [
                ['id' => null, 'column' => 0, 'widget' => 'system.topbar', 'enabled' => true],
                ['id' => null, 'column' => 0, 'widget' => 'system.logo', 'enabled' => true],
                ['id' => null, 'column' => 0, 'widget' => 'system.nav', 'enabled' => true],
            ]]],
        ])])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(3, LayoutPlacement::where('theme', 'plaza')->where('region', 'header')->count());
        $this->get('/')->assertOk()->assertSee('nav-bar', false);
    }

    public function test_header_pieces_are_offered_only_in_a_header_region(): void
    {
        $this->assertSame(['header'], \App\Support\Widgets::get('system.logo')['regions']);
        $this->assertTrue(\App\Support\Widgets::get('system.header')['system']);
        $this->assertFalse(\App\Support\Widgets::allowedIn('system.logo', 'content_top'));
    }
}
