<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The customer's Message to Store is on the queue ROW, not only in the
 * drawer. The owner missed a customer's message because the row said
 * nothing and the drawer was never opened for that order.
 */
class FulfillmentRowMessageTest extends TestCase
{
    use RefreshDatabase;

    private function queueUrl(): string
    {
        return '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment?filter=to_pack';
    }

    private function boot(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('k')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('s')]);
        $this->actingAs(User::factory()->create());
    }

    private function order(?string $comment): Order
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Processing'], ['sort_order' => 2]);
        \Extensions\CourierFulfillment\PlacementMap::put($status->id, 'to_pack');

        return Order::create([
            'store_id' => 1, 'order_status_id' => $status->id, 'comment' => $comment,
            'first_name' => 'June', 'last_name' => 'Sillacos', 'email' => 'june@example.com', 'phone' => '1',
            'shipping_first_name' => 'June', 'shipping_last_name' => 'Sillacos', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_state' => 'Cebu', 'shipping_postcode' => '6000',
            'payment_method' => 'bank_transfer', 'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ]);
    }

    public function test_a_customers_message_is_on_the_row(): void
    {
        $this->boot();
        $order = $this->order('pls add a handwritten note that says never stop playing');

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertSee('Message to Store')
            ->assertSee('pls add a handwritten note that says never stop playing');
    }

    public function test_a_row_without_a_message_carries_no_label(): void
    {
        $this->boot();
        $order = $this->order(null);

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('#' . $order->id, false)
            ->assertDontSee('v-tierrow__note', false);
    }
}
