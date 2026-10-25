<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantType;
use App\Models\VariantTypeValue;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A variation switched off in the admin is gone from the shop: not shown,
 * not addable, and not orderable from a cart that picked it up earlier.
 *
 * Reported 2026-10-16: the product page listed every variant whatever its
 * switch said, and checkout only ever asked about stock.
 */
class SwitchedOffVariantTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $short;

    private ProductVariant $long;

    private VariantTypeValue $shortValue;

    private VariantTypeValue $longValue;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);

        $this->product = Product::create([
            'store_id' => 1, 'name' => 'Instrument Cable', 'slug' => 'instrument-cable', 'sku' => 'CAB',
            'price' => 500, 'quantity' => 20, 'is_active' => true, 'has_variants' => true, 'track_stock' => true,
        ]);

        $type = VariantType::create(['product_id' => $this->product->id, 'name' => 'Length', 'sort_order' => 1]);
        $this->shortValue = VariantTypeValue::create(['variant_type_id' => $type->id, 'value' => '10 Ft', 'sort_order' => 1]);
        $this->longValue = VariantTypeValue::create(['variant_type_id' => $type->id, 'value' => '20 Ft', 'sort_order' => 2]);

        $this->short = $this->product->variants()->create([
            'sku' => 'CAB-10', 'name' => 'Length: 10 Ft', 'price' => 500, 'quantity' => 10, 'is_active' => true,
        ]);
        $this->short->values()->attach($this->shortValue->id);

        $this->long = $this->product->variants()->create([
            'sku' => 'CAB-20', 'name' => 'Length: 20 Ft', 'price' => 800, 'quantity' => 10, 'is_active' => false,
        ]);
        $this->long->values()->attach($this->longValue->id);
    }

    public function test_the_product_page_leaves_out_a_switched_off_variant(): void
    {
        $page = $this->get(route('product.show', $this->product->slug))->assertOk();

        $matrix = collect($page->viewData('variantMatrix'));
        $this->assertSame([$this->short->id], $matrix->pluck('variant_id')->values()->all());
        $page->assertSee('10 Ft')->assertDontSee('20 Ft');
    }

    public function test_a_product_whose_variants_are_all_off_offers_none(): void
    {
        $this->short->update(['is_active' => false]);

        $page = $this->get(route('product.show', $this->product->slug))->assertOk();

        $this->assertSame([], $page->viewData('variantMatrix'));
        $page->assertDontSee('10 Ft')->assertDontSee('20 Ft');
        $page->assertDontSee('In Stock')->assertSee('schema.org/OutOfStock', false);
        $this->assertSame(20, (int) $this->product->fresh()->quantity, 'The stored total is untouched.');
    }

    public function test_the_page_counts_only_the_stock_it_offers(): void
    {
        $page = $this->get(route('product.show', $this->product->slug))->assertOk();

        $this->assertSame(10, (int) $page->viewData('product')->quantity, 'The 20 Ft variant is off, so its 10 do not count.');
    }

    public function test_adding_a_switched_off_variant_says_so(): void
    {
        $this->postJson(route('cart.add'), [
            'product_id' => $this->product->id, 'variant_id' => $this->long->id, 'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['variant_id' => 'no longer available']);
    }

    public function test_the_cart_flags_a_variant_switched_off_after_it_was_added(): void
    {
        [$customer, , $cart] = $this->customerCartWith($this->short);
        $this->short->update(['is_active' => false]);

        $page = $this->actingAs($customer, 'customer')->get(route('cart.index'))->assertOk();

        $this->assertSame([$cart->items()->value('id')], $page->viewData('unavailableIds'));
        $this->assertSame([], $page->viewData('outOfStockIds'), 'Unavailable, not merely out of stock.');
        $page->assertSee('NO LONGER AVAILABLE');
    }

    public function test_checkout_refuses_a_variant_switched_off_after_it_was_added(): void
    {
        [$customer, $address, $cart] = $this->customerCartWith($this->short);
        $this->short->update(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no longer available');

        app(CheckoutService::class)->placeOrder($customer, $address, null, 'cod', 'flat', $cart);
    }

    public function test_checkout_refuses_a_product_switched_off_after_it_was_added(): void
    {
        [$customer, $address, $cart] = $this->customerCartWith($this->short);
        $this->product->update(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no longer available');

        app(CheckoutService::class)->placeOrder($customer, $address, null, 'cod', 'flat', $cart);
    }

    /** @return array{0: Customer, 1: CustomerAddress, 2: Cart} */
    private function customerCartWith(ProductVariant $variant): array
    {
        $customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane@example.test', 'password' => bcrypt('secret'),
        ]);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'address_1' => '1 Test St', 'city' => 'Manila', 'postcode' => '1000',
        ]);
        $cart = Cart::create(['store_id' => 1, 'customer_id' => $customer->id]);
        $cart->items()->create([
            'product_id' => $this->product->id, 'product_variant_id' => $variant->id,
            'name' => $this->product->name, 'variant_label' => $variant->name,
            'price' => $variant->price, 'quantity' => 1,
        ]);

        return [$customer, $address, $cart];
    }
}
