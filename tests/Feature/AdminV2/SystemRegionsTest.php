<?php

namespace Tests\Feature\AdminV2;

use App\Support\Regions;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * Placeable system widgets: a theme may declare a region whose contents
 * default to its own pieces (a header, a product page body), which the
 * storefront shows until the merchant arranges the region. The default
 * theme declares none and keeps its fixed pages.
 */
class SystemRegionsTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    public function test_a_theme_declares_a_header_region_with_its_shipped_piece_and_the_default_does_not(): void
    {
        $this->makeSampleTheme();

        $this->assertSame([], Regions::defaults('header', 'venta'));
        $this->assertSame([['widget' => 'system.header', 'title' => null, 'config' => []]], Regions::defaults('header', 'sample'));
        $this->assertSame('*', Regions::page('header', 'sample'));
    }

    public function test_header_pieces_are_offered_only_in_a_header_region(): void
    {
        $this->assertSame(['header'], Widgets::get('system.logo')['regions']);
        $this->assertTrue(Widgets::get('system.header')['system']);
        $this->assertFalse(Widgets::allowedIn('system.logo', 'content_top'));
    }

    public function test_the_default_theme_keeps_its_fixed_product_page(): void
    {
        app(\App\Services\ExtensionManager::class)->install('reviews');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $product = \App\Models\Product::create(['store_id' => 1, 'name' => 'Region Fuzz', 'slug' => 'region-fuzz', 'sku' => 'RF1', 'price' => 1200, 'quantity' => 3, 'is_active' => true]);

        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('addToCart(', false);
        $this->assertSame([], Regions::defaults('product_main', 'venta'));
    }
}
