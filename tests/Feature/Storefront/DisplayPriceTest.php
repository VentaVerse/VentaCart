<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductSpecial;
use App\Models\ProductVariant;
use App\Services\BadgeService;
use App\Services\CartService;
use App\Support\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every storefront price comes from Pricing::display(), which asks the same
 * price resolver the cart does, so a card never shows one price and the
 * cart charge another.
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
            'product_id' => $product->id, 'price_type' => 'fixed',
            'date_start' => null, 'date_end' => now()->addWeek(),
        ]);
    }

    private function discount(Product $product, array $attributes): ProductDiscount
    {
        return ProductDiscount::create($attributes + [
            'product_id' => $product->id, 'customer_group_id' => null, 'quantity' => 1, 'price_type' => 'fixed',
        ]);
    }

    public function test_a_guest_sees_a_special_on_cards_and_pays_it_in_the_cart(): void
    {
        $product = $this->product();
        $this->special($product, ['value' => 800, 'date_end' => now()->addMonth()]);

        $price = Pricing::display($product->fresh());
        $this->assertSame(800.0, $price->price);
        $this->assertSame(1000.0, $price->regularPrice);
        $this->assertTrue($price->isSale());
        $this->assertSame('special', $price->source);
        $this->assertNotNull($price->special);
        $this->assertSame(now()->addMonth()->toDateString(), $price->endsAt?->toDateString());
        $this->assertSame(20, $price->savingPercent());
        $this->assertSame('special', $price->toArray()['source']);
        $this->assertArrayNotHasKey('display_mode', $price->toArray());

        $this->get(route('search.results', ['q' => 'Demo Kettle']))
            ->assertOk()
            ->assertSeeInOrder(['product-price-old', money(1000), 'product-price-sale', money(800)], false);

        $item = app(CartService::class)->add($product->id);
        $this->assertSame($price->price, (float) $item->price);
    }

    public function test_discount_below_regular_shows_slashed(): void
    {
        $product = $this->product();
        $this->discount($product, ['customer_group_id' => $this->default->id, 'value' => 900]);

        $price = Pricing::display($product);
        $this->assertSame(900.0, $price->price);
        $this->assertTrue($price->isSale());
        $this->assertSame('discount', $price->source);
        // A discount has no end, and is not a special.
        $this->assertNull($price->special);
        $this->assertNull($price->endsAt);
        $this->assertNull($price->toArray()['ends_at']);

        $this->get(route('search.results', ['q' => 'Demo Kettle']))
            ->assertOk()
            ->assertSeeInOrder(['product-price-old', money(1000), 'product-price-sale', money(900)], false);
    }

    public function test_a_rule_not_below_the_regular_price_shows_the_regular_price(): void
    {
        $product = $this->product();
        $this->special($product, ['value' => 1100]);
        $this->discount($product, ['value' => 1000]);

        $price = Pricing::display($product);
        $this->assertSame(1000.0, $price->price);
        $this->assertFalse($price->isSale());
        $this->assertNull($price->source);
        $this->assertNull($price->special);

        $html = $this->get(route('search.results', ['q' => 'Demo Kettle']))->assertOk()->getContent();
        $this->assertStringContainsString(money(1000), $html);
        $this->assertStringNotContainsString('product-price-old', $html);
    }

    public function test_the_price_is_the_lowest_rule_the_cart_would_charge(): void
    {
        $product = $this->product();
        $this->special($product, ['value' => 850]);
        $this->special($product, ['value' => 820]);
        // A discount from five units does not apply to one.
        $this->discount($product, ['value' => 600, 'quantity' => 5]);
        // Expired, and for another group: neither applies.
        $this->special($product, ['value' => 100, 'date_end' => now()->subDays(2)]);
        $other = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $this->discount($product, ['value' => 200, 'customer_group_id' => $other->id]);

        $price = Pricing::display($product);
        $this->assertSame(820.0, $price->price);
        $this->assertSame('special', $price->source);
        $this->assertSame(CartService::resolvePrice($product, 1000.0), $price->price);

        $five = Pricing::display($product, null, 5);
        $this->assertSame(600.0, $five->price);
        $this->assertSame('discount', $five->source);
        $this->assertNull($five->endsAt);
    }

    public function test_a_signed_in_customer_gets_their_own_group(): void
    {
        $product = $this->product();
        $resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $this->discount($product, ['value' => 800, 'customer_group_id' => $this->default->id]);
        $this->discount($product, ['value' => 750, 'customer_group_id' => $resellers->id]);

        $this->assertSame(800.0, Pricing::display($product)->price);

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
        $this->special($product, ['value' => 800]);

        $price = Pricing::display($product, $variant->id);
        $this->assertSame(1200.0, $price->regularPrice);
        $this->assertSame(1000.0, $price->price);
        $this->assertSame(CartService::resolvePrice($product, 1200.0), $price->price);
    }

    public function test_the_sale_badge_and_recently_viewed_follow_the_same_price(): void
    {
        $product = $this->product();
        $this->special($product, ['value' => 750]);

        $badges = collect(app(BadgeService::class)->getBadgesForProduct($product->fresh()));
        $this->assertSame('-25%', $badges->firstWhere('type', 'sale')['label'] ?? null);

        $this->getJson(route('recently-viewed', ['ids' => [$product->id]]))
            ->assertOk()
            ->assertJsonPath('0.formatted_price', money(1000))
            ->assertJsonPath('0.formatted_sale_price', money(750))
            ->assertJsonPath('0.override_price', null);
    }

    public function test_a_group_discount_shows_slashed_but_earns_no_sale_badge(): void
    {
        $product = $this->product();
        $resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $this->discount($product, ['value' => 800, 'customer_group_id' => $resellers->id]);

        $customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com',
            'password' => bcrypt('secret-password'), 'customer_group_id' => $resellers->id,
        ]);
        $this->actingAs($customer, 'customer');

        $price = Pricing::display($product->fresh());
        $this->assertSame(800.0, $price->price);
        $this->assertTrue($price->isSale());
        $this->assertFalse($price->isSpecial());

        $badges = collect(app(BadgeService::class)->getBadgesForProduct($product->fresh()));
        $this->assertNull($badges->firstWhere('type', 'sale'));
    }

    public function test_a_running_special_earns_the_sale_badge(): void
    {
        $product = $this->product();
        $this->special($product, ['value' => 900]);

        $price = Pricing::display($product->fresh());
        $this->assertTrue($price->isSale());
        $this->assertTrue($price->isSpecial());

        $badges = collect(app(BadgeService::class)->getBadgesForProduct($product->fresh()));
        $this->assertSame('-10%', $badges->firstWhere('type', 'sale')['label'] ?? null);

        // A special that is not below the regular price is no sale.
        $other = Product::create([
            'store_id' => 1, 'name' => 'Demo Mug', 'slug' => 'demo-mug', 'sku' => 'DEMO-002',
            'price' => 500, 'quantity' => 10, 'is_active' => true,
        ]);
        $this->special($other, ['value' => 600]);
        $this->assertFalse(Pricing::display($other->fresh())->isSpecial());
    }
}
