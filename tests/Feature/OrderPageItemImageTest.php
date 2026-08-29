<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each line on the customer's order page shows the thing they bought: the
 * variant's photo when the line captured one, else the product's main
 * image, and the name links to the product while it is still on sale.
 */
class OrderPageItemImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_line_shows_its_own_image_else_the_products_and_links_to_the_product(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1]);
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'password' => bcrypt('x')]);
        $live = Product::create(['store_id' => 1, 'name' => 'Cable', 'slug' => 'cable', 'sku' => 'C1', 'price' => 100, 'quantity' => 3, 'is_active' => true, 'image' => 'catalog/cable-main.jpg']);
        $gone = Product::create(['store_id' => 1, 'name' => 'Old Strap', 'slug' => 'old-strap', 'sku' => 'S1', 'price' => 50, 'quantity' => 0, 'is_active' => false, 'image' => 'catalog/strap.jpg']);
        $order = Order::create([
            'store_id' => 1, 'customer_id' => $customer->id, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'phone' => '1',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 150, 'total' => 150,
        ]);
        $order->products()->create(['product_id' => $live->id, 'name' => 'Cable', 'sku' => 'C1-RED', 'price' => 100, 'quantity' => 1, 'total' => 100, 'image' => 'catalog/cable-red.jpg', 'variant_label' => 'Red']);
        $order->products()->create(['product_id' => $live->id, 'name' => 'Cable', 'sku' => 'C1', 'price' => 100, 'quantity' => 1, 'total' => 100]);
        $order->products()->create(['product_id' => $gone->id, 'name' => 'Old Strap', 'sku' => 'S1', 'price' => 50, 'quantity' => 1, 'total' => 50]);

        $html = $this->actingAs($customer, 'customer')->get(route('account.orders.show', $order))->assertOk()->getContent();

        $this->assertStringContainsString(thumb_url('catalog/cable-red.jpg'), $html, 'The variant photo the line captured.');
        $this->assertStringContainsString(thumb_url('catalog/cable-main.jpg'), $html, 'A line without its own photo shows the product\'s main image.');
        $this->assertStringContainsString(thumb_url('catalog/strap.jpg'), $html);
        $this->assertStringContainsString('href="' . route('product.show', 'cable') . '"', $html);
        $this->assertStringNotContainsString(route('product.show', 'old-strap'), $html, 'A product no longer on sale is not a link.');
    }
}
