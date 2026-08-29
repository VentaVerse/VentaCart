<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * History rows the courier mapper wrote as "Courier status: {code}" are
 * rewritten once, on an existing store, to the sentence the mapper writes
 * today. A fresh install has nothing to rewrite.
 */
class RewriteCourierHistoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Installing the extension runs its migrations as a second batch, which
     * is exactly what makes a store "existing" to InstallState; so only the
     * existing-store test installs it, and the fresh-install test stays on
     * batch 1 like a real first deploy.
     */
    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function migration(): object
    {
        return require base_path('database/migrations/2026_08_30_180000_rewrite_courier_status_history_as_sentences.php');
    }

    private function order(string $provider, ?string $courierName): Order
    {
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['is_default' => true, 'sort_order' => 1]);
        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => uniqid('jane') . '@example.com', 'password' => bcrypt('x')]);
        $order = Order::create([
            'store_id' => 1, 'customer_id' => $customer->id, 'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => $customer->email, 'phone' => '1',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 100, 'total' => 100,
        ])->forceFill(['courier_provider' => $provider, 'shipping_courier_name' => $courierName]);
        $order->save();

        return $order;
    }

    public function test_an_existing_store_reads_its_old_rows_in_the_couriers_words(): void
    {
        $this->installExtension();
        DB::table('migrations')->insert(['migration' => '9999_99_99_000000_later_deploy', 'batch' => 2]);
        $quadx = $this->order('quadx', 'QuadX');
        $spx = $this->order('spx', 'SPX');
        $nameless = $this->order('quadx', null);
        $quadx->histories()->create(['order_status_id' => $quadx->order_status_id, 'comment' => 'Courier status: out_for_delivery', 'notify_customer' => true]);
        $spx->histories()->create(['order_status_id' => $spx->order_status_id, 'comment' => 'Courier status: 1001', 'notify_customer' => true]);
        $nameless->histories()->create(['order_status_id' => $nameless->order_status_id, 'comment' => 'Courier status: canceled', 'notify_customer' => false]);
        $quadx->histories()->create(['order_status_id' => $quadx->order_status_id, 'comment' => 'Packed by Ana.', 'notify_customer' => false]);

        $this->migration()->up();

        $this->assertDatabaseHas('order_histories', ['order_id' => $quadx->id, 'comment' => 'QuadX: Out for delivery']);
        $this->assertDatabaseHas('order_histories', ['order_id' => $spx->id, 'comment' => 'SPX: ' . \Extensions\CourierFulfillment\CourierStatusLabel::for('spx', '1001')]);
        $this->assertDatabaseHas('order_histories', ['order_id' => $nameless->id, 'comment' => 'QuadX: Canceled']);
        $this->assertDatabaseHas('order_histories', ['order_id' => $quadx->id, 'comment' => 'Packed by Ana.']);
        $this->assertDatabaseMissing('order_histories', ['comment' => 'Courier status: out_for_delivery']);
    }

    public function test_a_fresh_install_touches_nothing(): void
    {
        $order = $this->order('quadx', 'QuadX');
        $order->histories()->create(['order_status_id' => $order->order_status_id, 'comment' => 'Courier status: for_pickup']);

        $this->migration()->up();

        $this->assertDatabaseHas('order_histories', ['comment' => 'Courier status: for_pickup']);
    }
}
