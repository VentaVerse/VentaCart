<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A variant keeps its identity across a save.
 *
 * syncVariants() used to delete every variant and build them again, so a
 * routine product edit churned every id. order_products.product_variant_id is
 * a foreign key with ON DELETE SET NULL, which means each save quietly cut
 * the link between an order and the exact colourway it sold. The
 * denormalised sku and label on the order line survived, so the order still
 * READ correctly to a human; what was lost was the machine link, and with it
 * reordering, stock reconciliation and the ERP's mapping.
 *
 * legacy_oc_id went the same way, on every save, for every migrated product.
 */
class VariantIdentitySurvivesSaveTest extends TestCase
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

    private function payload(array $variants): array
    {
        return [
            'store_id' => 1, 'name' => 'Strap', 'price' => 1000, 'is_active' => 1,
            'variant_types' => [['name' => 'Colour', 'values' => ['Blue', 'Red']]],
            'variants' => $variants,
        ];
    }

    private function variant(string $sku, string $key, array $overrides = []): array
    {
        return array_merge([
            'sku' => $sku, 'value_keys' => [$key], 'modifier_sign' => '+',
            'modifier_amount' => '0.00', 'quantity' => 1, 'is_active' => '1',
        ], $overrides);
    }

    private function save(Product $product, array $variants)
    {
        return $this->put(route('admin.products.update', $product), $this->payload($variants));
    }

    public function test_a_variants_id_survives_an_unrelated_edit(): void
    {
        $product = $this->product();
        $this->save($product, [$this->variant('STRAP-BLUE', '0_0')])->assertRedirect();

        $before = ProductVariant::where('sku', 'STRAP-BLUE')->first()->id;

        $this->save($product, [$this->variant('STRAP-BLUE', '0_0', ['quantity' => 99])])->assertRedirect();

        $this->assertSame($before, ProductVariant::where('sku', 'STRAP-BLUE')->first()->id);
        $this->assertSame(99, ProductVariant::where('sku', 'STRAP-BLUE')->first()->quantity);
    }

    /** The reason any of this matters. */
    public function test_an_order_line_keeps_pointing_at_what_it_sold(): void
    {
        $product = $this->product();
        $this->save($product, [$this->variant('STRAP-BLUE', '0_0')])->assertRedirect();

        $variant = ProductVariant::where('sku', 'STRAP-BLUE')->first();

        $orderId = DB::table('orders')->insertGetId([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'B',
            'email' => 'a@b.test', 'total' => 1000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $lineId = DB::table('order_products')->insertGetId([
            'order_id' => $orderId, 'product_id' => $product->id,
            'product_variant_id' => $variant->id, 'name' => 'Strap',
            'sku' => 'STRAP-BLUE', 'variant_label' => 'Blue',
            'quantity' => 1, 'price' => 1000, 'total' => 1000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // A merchant edits the product's stock. Nothing to do with the order.
        $this->save($product, [$this->variant('STRAP-BLUE', '0_0', ['quantity' => 42])])->assertRedirect();

        $this->assertSame(
            $variant->id,
            DB::table('order_products')->where('id', $lineId)->value('product_variant_id'),
            'the order line must still know which variant it sold'
        );
    }

    public function test_legacy_ids_are_not_thrown_away_on_every_save(): void
    {
        $product = $this->product();
        $this->save($product, [$this->variant('STRAP-BLUE', '0_0')])->assertRedirect();

        ProductVariant::where('sku', 'STRAP-BLUE')->update(['legacy_oc_id' => 4321]);

        $this->save($product, [$this->variant('STRAP-BLUE', '0_0', ['quantity' => 7])])->assertRedirect();

        $this->assertSame(4321, ProductVariant::where('sku', 'STRAP-BLUE')->first()->legacy_oc_id);
    }

    public function test_a_variant_the_merchant_removed_is_still_removed(): void
    {
        $product = $this->product();
        $this->save($product, [
            $this->variant('STRAP-BLUE', '0_0'),
            $this->variant('STRAP-RED', '0_1'),
        ])->assertRedirect();

        $this->save($product, [$this->variant('STRAP-BLUE', '0_0')])->assertRedirect();

        $this->assertSame(1, $product->fresh()->variants()->count());
        $this->assertNull(ProductVariant::where('sku', 'STRAP-RED')->first());
    }

    public function test_the_option_values_do_not_double_up_across_saves(): void
    {
        $product = $this->product();

        foreach (range(1, 3) as $ignored) {
            $this->save($product, [$this->variant('STRAP-BLUE', '0_0')])->assertRedirect();
        }

        // attach() would have stacked a row per save; sync() keeps one.
        $this->assertSame(1, ProductVariant::where('sku', 'STRAP-BLUE')->first()->values()->count());
    }

    public function test_dropping_every_variant_type_still_clears_the_variants(): void
    {
        $product = $this->product();
        $this->save($product, [$this->variant('STRAP-BLUE', '0_0')])->assertRedirect();

        $this->put(route('admin.products.update', $product), [
            'store_id' => 1, 'name' => 'Strap', 'price' => 1000, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame(0, $product->fresh()->variants()->count());
        $this->assertFalse((bool) $product->fresh()->has_variants);
    }
}
