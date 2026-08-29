<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ShippingFakeCourier;
use Tests\TestCase;

/**
 * A mapping saved AFTER a parcel reached a courier status must still move
 * the order on the next poll. The poll only applied the map when the
 * fetched status differed from the stored one, so a live order sat on
 * received_at_pickup_hub for days: the status never changed again, and the
 * mapping the merchant added later was never read.
 */
class PollAppliesLateMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        if (class_exists(\Extensions\CourierFulfillment\CourierManager::class, false)) {
            $ref = new \ReflectionProperty(\Extensions\CourierFulfillment\CourierManager::class, 'registered');
            $ref->setAccessible(true);
            $registered = $ref->getValue();
            unset($registered['fakeship']);
            $ref->setValue(null, $registered);
        }
        parent::tearDown();
    }

    public function test_a_mapping_added_after_the_status_arrived_moves_the_order_on_the_next_poll(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        // The extension registers its commands at provider boot; Artisan's
        // list was resolved before that in a test, so register it here too
        // (same as SpxPollCourierStatusTest).
        $this->app[\Illuminate\Contracts\Console\Kernel::class]
            ->registerCommand(new \Extensions\CourierFulfillment\Console\Commands\PollCourierStatus());
        \Extensions\CourierFulfillment\CourierManager::register(ShippingFakeCourier::class);
        Setting::create(['group' => 'courier', 'key' => 'fakeship_enabled', 'value' => '1']);

        $processing = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 3]);

        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $processing->id,
            'first_name' => 'J', 'last_name' => 'S', 'email' => 'j@example.com', 'phone' => '1',
            'shipping_first_name' => 'J', 'shipping_last_name' => 'S', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 1, 'total' => 1,
        ]);
        // The parcel ALREADY reported in_transit (the fake answers in_transit
        // on every fetch), before any mapping for it existed.
        $order->forceFill(['courier_provider' => 'fakeship', 'courier_tracking_number' => 'FS-1', 'courier_status' => 'in_transit'])->save();

        // The merchant maps it afterwards.
        Setting::create(['group' => 'courier', 'key' => 'fakeship_status_map', 'value' => json_encode([
            'in_transit' => ['order_status_id' => $shipped->id, 'notify' => false],
        ])]);

        $this->artisan('courier:poll-status')->assertSuccessful();

        $this->assertSame($shipped->id, $order->fresh()->order_status_id, 'The poll saw no status change and never read the new mapping.');

        // And a second poll leaves it alone: apply() is idempotent.
        $this->artisan('courier:poll-status')->assertSuccessful();
        $this->assertSame(1, $order->fresh()->histories()->where('order_status_id', $shipped->id)->count());
    }
}
