<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Store;
use App\Services\ExtensionManager;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The path the business uses most. It told nobody and moved no stock.
 */
class ErpStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        // TemplateMailService::send() finds no active template and quietly
        // returns false without this, so Mail::assertSentCount() would pass
        // whether or not the email path actually fired.
        $this->seed(EmailTemplateSeeder::class);
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(ExtensionManager::class)->install('erp-sync');
        app(ExtensionManager::class)->bootEnabled();
        $this->apiKey('erp-test-token');
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2,
            'emails_customer' => true, 'deducts_stock' => true,
        ]);
        Mail::fake();
    }

    private function order(): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $this->packing->id,
        ]);
    }

    public function test_an_erp_status_update_emails_the_customer(): void
    {
        $order = $this->order();

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        Mail::assertSentCount(1);
    }

    public function test_an_erp_status_update_moves_stock(): void
    {
        $product = Product::create([
            'store_id' => 1, 'name' => 'Cable', 'slug' => 'cable',
            'price' => 100, 'quantity' => 5, 'track_stock' => true,
        ]);
        $order = $this->order();
        $order->products()->create([
            'product_id' => $product->id, 'name' => 'Cable', 'price' => 100, 'quantity' => 2, 'total' => 200,
        ]);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        $this->assertSame(3, $product->fresh()->quantity, 'Shipped deducts stock, whoever moved the order.');
    }

    public function test_setting_the_status_it_already_has_says_nothing(): void
    {
        $order = $this->order();
        $order->update(['order_status_id' => $this->shipped->id]);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/orders/' . $order->id . '/status', ['status_id' => $this->shipped->id])
            ->assertOk();

        Mail::assertNothingSent();
    }
}
