<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderGroupPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_discount_prices_order_line(): void
    {
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
        $product = Product::create([
            'store_id' => 1, 'name' => 'Demo Widget', 'slug' => 'demo-widget', 'sku' => 'DEMO-001', 'price' => 100, 'is_active' => true,
        ]);
        ProductDiscount::create([
            'product_id' => $product->id, 'customer_group_id' => $group->id,
            'quantity' => 1, 'price_type' => 'fixed', 'value' => 80,
        ]);

        $admin = User::factory()->create();

        $reseller = $this->actingAs($admin)->getJson(route('admin.orders.search-products', [
            'q' => 'Demo Widget', 'store_id' => 1, 'customer_group_id' => $group->id,
        ]))->assertOk()->json(0);

        $this->assertEquals(80, $reseller['group_price']);
        $this->assertSame('sale', $reseller['group_price_mode']);
        $this->assertEquals(100, $reseller['base_price']);

        $guest = $this->actingAs($admin)->getJson(route('admin.orders.search-products', [
            'q' => 'Demo Widget', 'store_id' => 1,
        ]))->assertOk()->json(0);

        $this->assertEquals(100, $guest['group_price']);
        $this->assertSame('regular', $guest['group_price_mode']);
    }
}
