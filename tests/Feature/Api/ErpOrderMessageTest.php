<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The customer's checkout message reaches the ERP on the order LIST as
 * well as the detail, so the ERP can flag an order that carries one
 * without fetching every order (owner request, 2026-08-30, after a
 * customer's message went unnoticed).
 */
class ErpOrderMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Setting::create(['group' => 'api', 'key' => 'erp_token', 'value' => Crypt::encryptString('erp-test-token')]);
    }

    private function order(?string $comment): Order
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Processing'], ['sort_order' => 2]);

        return Order::create([
            'store_id' => 1, 'order_status_id' => $status->id, 'comment' => $comment,
            'first_name' => 'June', 'last_name' => 'Sillacos', 'email' => 'june@example.com', 'phone' => '1',
            'shipping_first_name' => 'June', 'shipping_last_name' => 'Sillacos', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 1000, 'total' => 1000,
        ]);
    }

    public function test_the_list_carries_the_customers_message(): void
    {
        $this->order('pls add a handwritten note');
        $this->order(null);

        $rows = $this->withToken('erp-test-token')->getJson('/api/v1/orders')->assertOk()->json('data');

        $this->assertSame(['pls add a handwritten note', null], array_column($rows, 'comment'));
        $this->assertArrayHasKey('courier_comment', $rows[0]);
    }

    public function test_the_detail_carries_it_too(): void
    {
        $order = $this->order('pickup today please');

        $this->withToken('erp-test-token')->getJson('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('comment', 'pickup today please');
    }
}
