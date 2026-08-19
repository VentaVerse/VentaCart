<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Store;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner question, 2026-08-20: "if reviews is disabled or uninstalled, does the
 * REVIEW tab on the front end disappear?"
 *
 * It did not. The storefront carried controls belonging to extensions that were
 * not installed, so a shop that had switched a module off still advertised it:
 * a REVIEWS tab reading "(0)" over an empty panel, and a NOTIFY ME WHEN IN
 * STOCK button whose fetch went to a route that no longer existed.
 *
 * Neither crashed, which is why nobody noticed. ProductController already
 * guards every review QUERY with App\Support\Reviews::enabled(), and the
 * notify button posts a hardcoded URL string rather than a route() call, so the
 * failure was a dead control rather than a 500. The guards existed one layer
 * too low: the data was gated, the markup was not.
 *
 * Core asks about an extension only through something core owns: a support
 * helper, or Route::has(). It never touches the extension's classes or route
 * objects directly. Same rule that fixed the Tag/BlogPost and Blog/SEO
 * couplings on 2026-08-19.
 */
class StorefrontExtensionLeftoversTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $quantity = 5): Product
    {
        Store::firstOrCreate(['id' => 1], ['name' => 'Test Store', 'code' => 'test']);

        return Product::create([
            'store_id' => 1,
            'name' => 'Test Pedal',
            'slug' => 'test-pedal',
            'sku' => 'TP-001',
            'description' => '<p>A pedal.</p>',
            'price' => 1000,
            'quantity' => $quantity,
            'is_active' => true,
            'track_stock' => true,
        ]);
    }

    // --- reviews -------------------------------------------------------

    public function test_the_reviews_tab_is_absent_when_the_extension_is_not_installed(): void
    {
        $product = $this->product();

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertDontSee('REVIEWS', false);
    }

    public function test_the_reviews_tab_is_present_when_the_extension_is_installed(): void
    {
        app(ExtensionManager::class)->install('reviews');
        app(ExtensionManager::class)->bootEnabled();

        $product = $this->product();

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('REVIEWS', false);
    }

    // --- stock alerts --------------------------------------------------

    /**
     * The button only appears for an out of stock product, so the fixture has
     * to be out of stock or this test would pass for the wrong reason.
     */
    public function test_the_notify_me_button_is_absent_when_stock_alerts_is_not_installed(): void
    {
        $product = $this->product(0);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertDontSee('NOTIFY ME WHEN IN STOCK', false);
    }

    public function test_the_notify_me_button_is_present_when_stock_alerts_is_installed(): void
    {
        app(ExtensionManager::class)->install('stock-alerts');
        app(ExtensionManager::class)->bootEnabled();

        $product = $this->product(0);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee('NOTIFY ME WHEN IN STOCK', false);
    }

    /**
     * The surfaces that were already correct, pinned so a future change cannot
     * quietly un-guard them. Checkout asks Route::has('checkout.coupon.apply')
     * and the sitemap's blog block is gated on a collection the controller
     * fills only when the Blog extension is loadable.
     */
    public function test_the_sitemap_survives_without_the_blog_extension(): void
    {
        $this->get('/sitemap')->assertOk()->assertDontSee('All Posts', false);
    }
}
