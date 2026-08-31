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
        app(\App\Services\ExtensionManager::class)->install(ThemeService::registryName('plaza'));
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

    public function test_plazas_panel_offers_the_shipped_row_and_saving_makes_it_the_merchants(): void
    {
        app(\App\Services\ExtensionManager::class)->install('theme');
        $this->usePlaza();
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        // Decoupled: Plaza's shipped pieces show in its own panel.
        $html = $this->get(route('admin.plaza.panel', ['panel' => 'builder', 'page' => '*']))->assertOk()->getContent();
        preg_match('/id="pp-config"[^>]*>(.*?)<\/script>/s', $html, $m);
        $config = json_decode(html_entity_decode($m[1]), true);
        $header = collect($config['layouts']['*']['rows']['header'] ?? []);
        $this->assertTrue($header->contains(fn ($u) => ! empty($u['shipped'])));

        $this->postJson(route('admin.plaza.editor.save'), ['page' => '*', 'layout' => ['columns' => ['left' => 0, 'right' => 0], 'settings' => [], 'rows' => ['header' => [['id' => null, 'columns' => [8], 'enabled' => true, 'style' => [], 'cols' => [[
            ['id' => null, 'widget' => 'system.topbar', 'enabled' => true, 'config' => []],
            ['id' => null, 'widget' => 'system.logo', 'enabled' => true, 'config' => []],
            ['id' => null, 'widget' => 'system.nav', 'enabled' => true, 'config' => []],
        ]]]]]], 'colors' => [], 'fonts' => [], 'theme_settings' => []])->assertOk();

        $this->assertSame(3, LayoutPlacement::where('theme', 'plaza')->where('region', 'header')->count());
        $this->get('/')->assertOk()->assertSee('nav-bar', false);
    }

    public function test_header_pieces_are_offered_only_in_a_header_region(): void
    {
        $this->assertSame(['header'], \App\Support\Widgets::get('system.logo')['regions']);
        $this->assertTrue(\App\Support\Widgets::get('system.header')['system']);
        $this->assertFalse(\App\Support\Widgets::allowedIn('system.logo', 'content_top'));
    }

    private function product(): \App\Models\Product
    {
        app(\App\Services\ExtensionManager::class)->install('reviews');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        return \App\Models\Product::create(['store_id' => 1, 'name' => 'Region Fuzz', 'slug' => 'region-fuzz', 'sku' => 'RF1', 'price' => 1200, 'quantity' => 3, 'is_active' => true]);
    }

    public function test_the_product_page_renders_its_shipped_pieces_until_arranged(): void
    {
        $this->usePlaza();
        $product = $this->product();
        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Region Fuzz', $html);
        $this->assertStringContainsString('addToCart(', $html, 'The buy box piece is there.');
        $this->assertStringContainsString('product-description', $html, 'The description piece is there.');
    }

    public function test_an_arranged_product_page_shows_only_what_was_placed(): void
    {
        $this->usePlaza();
        $product = $this->product();
        LayoutPlacement::create(['theme' => 'plaza', 'page_type' => 'product', 'region' => 'product_main', 'widget' => 'system.product_buy', 'sort_order' => 0, 'is_enabled' => true]);
        LayoutPlacement::create(['theme' => 'plaza', 'page_type' => 'product', 'region' => 'product_main', 'widget' => 'content.text', 'config' => ['content' => 'Ships from Manila'], 'sort_order' => 1, 'is_enabled' => true]);

        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();
        $this->assertStringContainsString('addToCart(', $html);
        $this->assertStringContainsString('Ships from Manila', $html);
        $this->assertStringNotContainsString('product-description', $html, 'The tabs piece was not placed.');
    }

    public function test_plazas_panel_arranges_the_product_page_body_as_a_region(): void
    {
        app(\App\Services\ExtensionManager::class)->install('theme');
        $this->usePlaza();
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        $html = $this->get(route('admin.plaza.panel', ['panel' => 'builder', 'page' => 'product']))->assertOk()->getContent();
        preg_match('/id="pp-config"[^>]*>(.*?)<\/script>/s', $html, $m);
        $config = json_decode(html_entity_decode($m[1]), true);
        $this->assertTrue(collect($config['regions'])->contains(fn ($r) => $r['key'] === 'product_main' && $r['page'] === 'product' && $r['system']));
        $this->assertContains('system.product_buy', collect($config['catalogue'])->pluck('key')->all());
    }

    public function test_venta_keeps_its_fixed_product_page(): void
    {
        $product = $this->product();
        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('addToCart(', false);
        $this->assertSame([], Regions::defaults('product_main', 'venta'));
    }
}
