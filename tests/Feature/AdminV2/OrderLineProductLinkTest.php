<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** On the admin order page a line's product opens its edit page while the product exists. */
class OrderLineProductLinkTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);

        return Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'J', 'last_name' => 'S', 'email' => 'j@example.com', 'phone' => '1',
            'shipping_first_name' => 'J', 'shipping_last_name' => 'S', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 1, 'total' => 1,
        ]);
    }

    public function test_a_line_whose_product_exists_links_to_its_edit_page(): void
    {
        $order = $this->order();
        $product = Product::create(['store_id' => 1, 'name' => 'Cable', 'slug' => 'cable', 'sku' => 'C1', 'price' => 100, 'quantity' => 3, 'is_active' => true]);
        $order->products()->create(['product_id' => $product->id, 'name' => 'Cable', 'sku' => 'C1', 'price' => 100, 'quantity' => 1, 'total' => 100]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('href="' . route('admin.products.edit', $product) . '"', false);
    }

    public function test_a_custom_line_or_a_deleted_product_stays_a_name(): void
    {
        $order = $this->order();
        $order->products()->create(['product_id' => null, 'name' => 'Handwritten note', 'sku' => null, 'price' => 0, 'quantity' => 1, 'total' => 0, 'is_custom' => true]);
        $gone = Product::create(['store_id' => 1, 'name' => 'Gone', 'slug' => 'gone', 'sku' => 'G1', 'price' => 1, 'quantity' => 1, 'is_active' => true]);
        $order->products()->create(['product_id' => $gone->id, 'name' => 'Gone', 'sku' => 'G1', 'price' => 1, 'quantity' => 1, 'total' => 1]);
        $gone->delete();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Handwritten note')
            ->assertSee('Gone')
            ->assertDontSee('/catalog/products/', false);
    }
}
