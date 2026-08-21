<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a refused variant sends the merchant.
 *
 * Every SKU check threw on the key `sku`, which is the PARENT's field on the
 * General tab. A merchant who typed a duplicate into row 31 of the Variants
 * tab was thrown to General, shown a bare string, and left to find the row.
 */
class ProductVariantErrorRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $variants, array $overrides = []): array
    {
        return array_merge([
            'store_id' => 1, 'name' => 'Strap', 'price' => 1000, 'is_active' => 1,
            'variant_types' => [['name' => 'Colour', 'values' => ['Blue', 'Red']]],
            'variants' => $variants,
        ], $overrides);
    }

    private function variant(string $sku, string $key = '0_0'): array
    {
        return ['sku' => $sku, 'value_keys' => [$key], 'modifier_sign' => '+',
                'modifier_amount' => '0.00', 'quantity' => 1, 'is_active' => '1'];
    }

    public function test_a_duplicate_inside_the_form_names_every_row_that_carries_it(): void
    {
        $response = $this->post(route('admin.products.store'), $this->payload([
            $this->variant('SAME'),
            $this->variant('SAME', '0_1'),
        ]));

        // Both rows, not just the first: naming one of the two involved is
        // half an answer.
        $response->assertSessionHasErrors(['variants.0.sku', 'variants.1.sku']);
        $response->assertSessionDoesntHaveErrors('sku');
    }

    public function test_a_sku_owned_by_another_product_lands_on_the_row_that_typed_it(): void
    {
        Product::create(['store_id' => 1, 'name' => 'Other', 'slug' => 'other',
            'sku' => 'TAKEN', 'price' => 1, 'quantity' => 1]);

        $this->post(route('admin.products.store'), $this->payload([
            $this->variant('FINE'),
            $this->variant('TAKEN', '0_1'),
        ]))->assertSessionHasErrors('variants.1.sku');
    }

    public function test_a_sku_owned_by_another_products_variant_lands_on_the_row_too(): void
    {
        $other = Product::create(['store_id' => 1, 'name' => 'Other', 'slug' => 'other',
            'sku' => 'OTHER', 'price' => 1, 'quantity' => 1]);
        $other->variants()->create(['sku' => 'VTAKEN', 'price' => 1, 'quantity' => 1, 'is_active' => true]);

        $this->post(route('admin.products.store'), $this->payload([
            $this->variant('VTAKEN'),
        ]))->assertSessionHasErrors('variants.0.sku');
    }

    /** The parent's own SKU still reports against the parent's own field. */
    public function test_a_duplicate_parent_sku_still_lands_on_the_parent(): void
    {
        Product::create(['store_id' => 1, 'name' => 'Other', 'slug' => 'other',
            'sku' => 'TAKEN', 'price' => 1, 'quantity' => 1]);

        $this->post(route('admin.products.store'), $this->payload([], ['sku' => 'TAKEN']))
            ->assertSessionHasErrors('sku');
    }

    public function test_the_form_opens_on_the_tab_that_holds_the_refused_row(): void
    {
        $product = Product::create(['store_id' => 1, 'name' => 'Strap', 'slug' => 'strap',
            'sku' => 'STRAP', 'price' => 1000, 'quantity' => 0, 'has_variants' => true]);

        $this->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->payload([$this->variant('')]))
            ->assertRedirect();

        // The old mapping looked for `variants` and `variants.*` and never
        // matched `variants.0.sku`, so the merchant landed on General.
        $html = $this->followingRedirects()
            ->put(route('admin.products.update', $product), $this->payload([$this->variant('')]))
            ->getContent();

        $this->assertStringContainsString("tab: 'variants'", $html);
    }
}
