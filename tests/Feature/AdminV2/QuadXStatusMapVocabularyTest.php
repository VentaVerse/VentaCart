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
 * The QuadX status map offers the statuses QuadX actually sends, grouped the
 * way a merchant reads a parcel's journey (owner cherry-pick, 2026-08-30,
 * from the 59 QuadX lists). The nine it used to offer were a guess that
 * left a live order stranded on received_at_pickup_hub with no row to map
 * it by, and carried failed_pickup, which QuadX never sends
 * (failed_pickup_attempt is what arrives).
 */
class QuadXStatusMapVocabularyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('k')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('s')]);
        $this->actingAs(User::factory()->create());
    }

    private function url(): string
    {
        return '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment/quadx';
    }

    private function field(string $status): string
    {
        return 'name="settings[quadx_status_map][' . $status . '][order_status_id]"';
    }

    public function test_the_map_offers_the_thirty_cherry_picked_statuses_in_six_groups(): void
    {
        $page = $this->get($this->url())->assertOk();

        foreach (['Before pickup', 'Picked up, moving', 'Delivered', 'Failed attempt', 'Return to sender', 'Cancelled'] as $group) {
            $page->assertSee($group);
        }
        // The one that stranded a live order, and the one that actually arrives.
        $page->assertSee($this->field('received_at_pickup_hub'), false)
            ->assertSee($this->field('failed_pickup_attempt'), false)
            ->assertSee($this->field('accepted'), false)
            ->assertSee($this->field('arrived_at_rts_hub'), false);
        // Left out on purpose.
        $page->assertDontSee($this->field('for_release'), false)
            ->assertDontSee($this->field('claimed'), false)
            ->assertDontSee($this->field('lost'), false)
            ->assertDontSee($this->field('dws_update'), false);

        $quadx = new \Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment();
        $this->assertCount(31, $quadx->mappableStatuses());
        $this->assertCount(31, array_unique($quadx->mappableStatuses()), 'A status sits in two groups.');
        $this->assertSame('in_transit', $quadx->statusPhase('for_transfer')['phase'], 'for_transfer is a parcel on the move (owner, 2026-08-30).');
    }

    public function test_a_status_the_store_has_received_but_the_list_lacks_still_gets_a_row(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);
        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'J', 'last_name' => 'S', 'email' => 'j@example.com', 'phone' => '1',
            'shipping_first_name' => 'J', 'shipping_last_name' => 'S', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 1, 'total' => 1,
        ]);
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_status'   => 'transferred_to_partner',
            'courier_meta'     => json_encode(['timeline' => [['status' => 'for_pickup', 'at' => 1], ['status' => 'transferred_to_partner', 'at' => 2]]]),
        ])->save();

        $this->get($this->url())->assertOk()
            ->assertSee('Seen on your orders, not listed above')
            ->assertSee($this->field('transferred_to_partner'), false);
    }

    public function test_new_rows_arrive_with_their_suggestion_even_when_the_map_was_already_saved(): void
    {
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 3]);
        $handover = OrderStatus::create(['store_id' => 1, 'name' => 'Order Being Shipped', 'sort_order' => 4]);
        \Extensions\CourierFulfillment\PlacementMap::put($handover->id, 'to_handover');
        // The merchant saved a map before received_at_pickup_hub existed as a row.
        Setting::create(['group' => 'courier', 'key' => 'quadx_status_map', 'value' => json_encode([
            'picked_up' => ['order_status_id' => $shipped->id, 'notify' => false],
        ])]);

        $html = $this->get($this->url())->assertOk()->getContent();

        // received_at_pickup_hub suggests Shipped; accepted suggests the booked status.
        $this->assertMatchesRegularExpression(
            '/name="settings\[quadx_status_map\]\[received_at_pickup_hub\]\[order_status_id\]".*?<option value="' . $shipped->id . '" selected>/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/name="settings\[quadx_status_map\]\[accepted\]\[order_status_id\]".*?<option value="' . $handover->id . '" selected>/s',
            $html
        );
    }

    public function test_a_mapped_received_at_pickup_hub_moves_the_order(): void
    {
        $processing = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 2]);
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 3]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_status_map', 'value' => json_encode([
            'received_at_pickup_hub' => ['order_status_id' => $shipped->id, 'notify' => false],
        ])]);
        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $processing->id,
            'first_name' => 'J', 'last_name' => 'S', 'email' => 'j@example.com', 'phone' => '1',
            'shipping_first_name' => 'J', 'shipping_last_name' => 'S', 'shipping_address_1' => 'x',
            'shipping_city' => 'Cebu City', 'shipping_postcode' => '6000', 'subtotal' => 1, 'total' => 1,
        ]);
        $order->forceFill(['courier_provider' => 'quadx', 'courier_tracking_number' => 'GPZZ-1'])->save();

        $this->assertTrue(\Extensions\CourierFulfillment\StatusMapper::apply($order, 'received_at_pickup_hub'));
        $this->assertSame($shipped->id, $order->fresh()->order_status_id);
    }
}
