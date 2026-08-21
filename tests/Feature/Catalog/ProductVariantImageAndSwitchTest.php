<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A variant's photo and its for-sale switch, in the admin.
 *
 * The image column has existed since the table was created and nothing had
 * ever written to it. The switch existed as a bare checkbox, which is the
 * admin's multi-selection control, and read by isset() rather than by value.
 *
 * The dangerous part is neither control: saving a product DELETES every
 * variant and recreates it, so a field the form does not round-trip is
 * destroyed by an unrelated edit.
 */
class ProductVariantImageAndSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());
    }

    private function product(): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => 'Strap', 'slug' => 'strap',
            'sku' => 'STRAP', 'price' => 1000, 'quantity' => 0, 'has_variants' => true,
        ]);
    }

    private function payload(array $variants, array $overrides = []): array
    {
        return array_merge([
            'store_id' => 1,
            'name' => 'Strap',
            'price' => 1000,
            'is_active' => 1,
            'variant_types' => [['name' => 'Colour', 'values' => ['Blue', 'Red']]],
            'variants' => $variants,
        ], $overrides);
    }

    private function variant(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'STRAP-BLUE',
            'value_keys' => ['0_0'],
            'modifier_sign' => '+',
            'modifier_amount' => '0.00',
            'quantity' => 5,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_a_photo_chosen_in_the_admin_is_stored(): void
    {
        $product = $this->product();

        $this->put(route('admin.products.update', $product), $this->payload([
            $this->variant(['image' => 'products/variants/blue.jpg']),
        ]))->assertRedirect();

        $this->assertSame('products/variants/blue.jpg', ProductVariant::where('sku', 'STRAP-BLUE')->first()->image);
    }

    /**
     * The whole reason the carry-forward exists: this save never mentions the
     * image, and the writer deletes every variant before recreating it.
     */
    public function test_an_unrelated_save_does_not_destroy_the_photo(): void
    {
        $product = $this->product();

        $this->put(route('admin.products.update', $product), $this->payload([
            $this->variant(['image' => 'products/variants/blue.jpg']),
        ]))->assertRedirect();

        $this->put(route('admin.products.update', $product), $this->payload([
            $this->variant(['quantity' => 99]),
        ]))->assertRedirect();

        $fresh = ProductVariant::where('sku', 'STRAP-BLUE')->first();

        $this->assertSame(99, $fresh->quantity);
        $this->assertSame('products/variants/blue.jpg', $fresh->image);
    }

    public function test_clearing_the_photo_still_clears_it(): void
    {
        $product = $this->product();
        $this->put(route('admin.products.update', $product), $this->payload([
            $this->variant(['image' => 'products/variants/blue.jpg']),
        ]))->assertRedirect();

        // The control posts an empty string, which must beat the carry-forward
        // or a merchant could never take a photo off a variant again.
        $this->put(route('admin.products.update', $product), $this->payload([
            $this->variant(['image' => '']),
        ]))->assertRedirect();

        $this->assertNull(ProductVariant::where('sku', 'STRAP-BLUE')->first()->image);
    }

    /**
     * The switch posts 1 and its hidden companion posts 0. The writer read
     * isset(), which is correct for a bare checkbox and reads every switched
     * OFF variant as ON the moment the companion arrives.
     */
    public function test_a_variant_switched_off_is_saved_off(): void
    {
        $product = $this->product();

        $this->put(route('admin.products.update', $product), $this->payload([
            $this->variant(['sku' => 'STRAP-BLUE', 'is_active' => '0']),
            $this->variant(['sku' => 'STRAP-RED', 'value_keys' => ['0_1'], 'is_active' => '1']),
        ]))->assertRedirect();

        $this->assertFalse(ProductVariant::where('sku', 'STRAP-BLUE')->first()->is_active);
        $this->assertTrue(ProductVariant::where('sku', 'STRAP-RED')->first()->is_active);
    }

    public function test_a_variant_with_no_sku_is_refused_instead_of_silently_dropped(): void
    {
        $product = $this->product();

        $this->put(route('admin.products.update', $product), $this->payload([
            $this->variant(['sku' => '']),
        ]))->assertSessionHasErrors('variants.0.sku');

        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_the_list_shows_a_variants_photo_sku_stock_and_state_when_opened(): void
    {
        $product = $this->product();
        $product->variants()->create([
            'sku' => 'STRAP-BLUE', 'price' => 1200, 'quantity' => 3,
            'is_active' => false, 'image' => 'products/variants/blue.jpg',
        ]);

        $closed = $this->get(route('admin.products.index'))->assertOk();
        $closed->assertDontSee('STRAP-BLUE', false);

        $open = $this->get(route('admin.products.index', ['open' => $product->id]))->assertOk();
        $open->assertSee('STRAP-BLUE', false);
        $open->assertSee('1,200.00', false);
        $open->assertSee('products/variants/blue.jpg', false);
        $open->assertSee('Off', false);
    }

    public function test_the_list_finds_a_product_by_its_variant_sku(): void
    {
        $product = $this->product();
        $product->variants()->create(['sku' => 'STRAP-BLUE', 'price' => 1200, 'quantity' => 3, 'is_active' => true]);

        $this->get(route('admin.products.index', ['search' => 'STRAP-BLUE']))
            ->assertOk()
            ->assertSee('Strap', false);
    }

    public function test_the_list_flags_a_product_whose_worst_variant_is_out(): void
    {
        $product = $this->product();
        $product->variants()->create(['sku' => 'A', 'price' => 1, 'quantity' => 400, 'is_active' => true]);
        $product->variants()->create(['sku' => 'B', 'price' => 1, 'quantity' => 0, 'is_active' => true]);

        // The total still shows, because that is what gets reordered, but the
        // row no longer reads 400 as if nothing were wrong.
        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('v-stock--out', false)
            ->assertSee('1 out', false);
    }
}
