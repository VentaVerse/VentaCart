<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductSpecial;
use App\Models\ProductVariant;
use App\Services\BadgeService;
use App\Services\CartService;
use App\Support\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every storefront price comes from Pricing::display(), which picks the
 * special the same way the cart does, so a card never shows one price and
 * the cart charge another.
 */
class DisplayPriceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroup $default;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->default = CustomerGroup::create(['store_id' => 1, 'name' => 'Default', 'is_default' => true]);
    }

    private function product(float $price = 1000): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => 'Demo Kettle', 'slug' => 'demo-kettle', 'sku' => 'DEMO-001',
            'price' => $price, 'quantity' => 10, 'is_active' => true,
        ]);
    }

    private function special(Product $product, array $attributes): ProductSpecial
    {
        return ProductSpecial::create($attributes + [
            'product_id' => $product->id, 'customer_group_id' => null, 'quantity' => 1,
            'display_mode' => 'sale', 'priority' => 1, 'date_start' => null, 'date_end' => null,
        ]);
    }

    public function test_a_guest_sees_the_default_group_sale_on_cards_and_pays_it_in_the_cart(): void
    {
        $product = $this->product();
        $this->special($product, ['customer_group_id' => $this->default->id, 'price' => 800, 'date_end' => now()->addMonth()]);

        $price = Pricing::display($product->fresh());
        $this->assertSame(800.0, $price->price);
        $this->assertSame(1000.0, $price->regularPrice);
        $this->assertTrue($price->isSale());
        $this->assertSame('sale', $price->displayMode);
        $this->assertSame(now()->addMonth()->toDateString(), $price->endsAt?->toDateString());
        $this->assertSame(20, $price->savingPercent());

        $this->get(route('search.results', ['q' => 'Demo Kettle']))
            ->assertOk()
            ->assertSeeInOrder(['product-price-old', money(1000), 'product-price-sale', money(800)], false);

        $item = app(CartService::class)->add($product->id);
        $this->assertSame($price->price, (float) $item->price);
    }

    public function test_the_card_shows_an_override_as_a_plain_price(): void
    {
        $product = $this->product();
        $this->special($product, ['price' => 900, 'display_mode' => 'override']);

        $price = Pricing::display($product);
        $this->assertSame(900.0, $price->price);
        $this->assertFalse($price->isSale());

        $html = $this->get(route('search.results', ['q' => 'Demo Kettle']))->assertOk()->getContent();
        $this->assertStringContainsString(money(900), $html);
        $this->assertStringNotContainsString('product-price-old', $html);
    }

    public function test_the_special_is_chosen_the_way_the_cart_chooses_it(): void
    {
        $product = $this->product();
        // Quantity 1 is a deeper tier than quantity 0, so it wins over a lower price.
        $this->special($product, ['price' => 700, 'quantity' => 0, 'priority' => 0]);
        $this->special($product, ['price' => 850, 'quantity' => 1, 'priority' => 2]);
        $this->special($product, ['price' => 820, 'quantity' => 1, 'priority' => 1, 'display_mode' => 'override']);
        // A tier for five or more does not apply to one.
        $this->special($product, ['price' => 600, 'quantity' => 5]);
        // Expired, and for another group: neither applies.
        $this->special($product, ['price' => 100, 'date_end' => now()->subDays(2)]);
        $other = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $this->special($product, ['price' => 200, 'customer_group_id' => $other->id]);

        $price = Pricing::display($product);
        $this->assertSame(820.0, $price->price);
        $this->assertSame('override', $price->displayMode);
        $this->assertSame(CartService::resolvePrice($product, 1000.0), $price->price);

        $this->assertSame(600.0, Pricing::display($product, null, 5)->price);
    }

    public function test_a_signed_in_customer_gets_their_own_group(): void
    {
        $product = $this->product();
        $resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $this->special($product, ['price' => 800, 'customer_group_id' => $this->default->id]);
        $this->special($product, ['price' => 750, 'customer_group_id' => $resellers->id]);

        $customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com',
            'password' => bcrypt('secret-password'), 'customer_group_id' => $resellers->id,
        ]);
        $this->actingAs($customer, 'customer');

        $this->assertSame(750.0, Pricing::display($product)->price);
    }

    public function test_a_variant_price_carries_the_product_special(): void
    {
        $product = $this->product();
        $product->update(['has_variants' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'DEMO-001-L', 'price' => 1200, 'quantity' => 5, 'is_active' => true]);
        $this->special($product, ['price' => 800]);

        $price = Pricing::display($product, $variant->id);
        $this->assertSame(1200.0, $price->regularPrice);
        $this->assertSame(1000.0, $price->price);
        $this->assertSame(CartService::resolvePrice($product, 1200.0), $price->price);
    }

    public function test_the_sale_badge_and_recently_viewed_follow_the_same_price(): void
    {
        $product = $this->product();
        $this->special($product, ['customer_group_id' => $this->default->id, 'price' => 750]);

        $badges = collect(app(BadgeService::class)->getBadgesForProduct($product->fresh()));
        $this->assertSame('-25%', $badges->firstWhere('type', 'sale')['label'] ?? null);

        $this->getJson(route('recently-viewed', ['ids' => [$product->id]]))
            ->assertOk()
            ->assertJsonPath('0.formatted_price', money(1000))
            ->assertJsonPath('0.formatted_sale_price', money(750));
    }
}
