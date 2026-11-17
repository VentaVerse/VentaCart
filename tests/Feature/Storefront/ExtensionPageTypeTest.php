<?php

namespace Tests\Feature\Storefront;

use App\Support\StorefrontPageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * An extension's own storefront page (a shop page, say) becomes a page type
 * a theme can tell apart, once the extension registers it. A type whose
 * extension is off is absent.
 */
class ExtensionPageTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StorefrontPageType::register('demo_shop', 'Shop pages', ['demo.shop.']);
    }

    protected function tearDown(): void
    {
        StorefrontPageType::forget('demo_shop');
        parent::tearDown();
    }

    public function test_a_registered_type_joins_the_page_list_after_cores_own(): void
    {
        $all = StorefrontPageType::all();
        $this->assertSame('Shop pages', $all['demo_shop']);
        $this->assertSame(array_keys(StorefrontPageType::TYPES), array_slice(array_keys($all), 0, count(StorefrontPageType::TYPES)), 'Core types keep their order, first.');

        $this->assertTrue(StorefrontPageType::exists('demo_shop'));
    }

    public function test_the_extensions_routes_resolve_to_its_type(): void
    {
        $this->assertSame('demo_shop', StorefrontPageType::forRoute('demo.shop.show'));
        $this->assertSame('product', StorefrontPageType::forRoute('product.show'), "Core's routes stay core's.");

        Route::get('/demo-shop/{slug}', fn () => StorefrontPageType::current())->name('demo.shop.show');
        $this->get('/demo-shop/demo-store')->assertOk()->assertSee('demo_shop');
    }

    public function test_core_types_cannot_be_taken_over_and_names_are_checked(): void
    {
        foreach (['product', 'quickview', 'Shop Pages', ''] as $bad) {
            try {
                StorefrontPageType::register($bad, 'X', ['x.']);
                $this->fail("[{$bad}] was accepted.");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        StorefrontPageType::register('demo_empty', 'X', []);
    }

    public function test_a_type_whose_extension_is_off_disappears(): void
    {
        StorefrontPageType::forget('demo_shop');

        $this->assertFalse(StorefrontPageType::exists('demo_shop'));
        $this->assertNull(StorefrontPageType::forRoute('demo.shop.show'));
    }
}
