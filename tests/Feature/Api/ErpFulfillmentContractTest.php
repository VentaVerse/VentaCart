<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\ShippingCourier;
use App\Models\Store;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What the ERP reads and writes about fulfilment through /api/v1: the
 * booking on every order payload, each status's queue placement, the
 * couriers' own choices on the serviceability answer, a booking that
 * takes every field the admin form takes, and the shipment recorded by
 * hand. The ERP mirrors this store's queue from exactly these answers.
 */
class ErpFulfillmentContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $manager = app(ExtensionManager::class);
        $manager->install('erp-sync');
        $manager->install('courier-fulfillment');
        $manager->bootEnabled();

        Setting::create(['group' => 'api', 'key' => 'erp_token', 'value' => Crypt::encryptString('erp-test-token')]);
        Setting::create(['group' => 'courier', 'key' => 'fulfillment_enabled', 'value' => '1']);

        // No call may reach a real courier.
        Http::fake();
    }

    private function api()
    {
        return $this->withToken('erp-test-token');
    }

    private function enableQuadx(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
    }

    /** A To Pack status and a To Handover status, placed. */
    private function statuses(): array
    {
        $pending = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Pending'], ['sort_order' => 1, 'is_default' => true]);
        PlacementMap::put($pending->id, 'to_pack');
        $shipped = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Order Being Shipped'], ['sort_order' => 4]);
        PlacementMap::put($shipped->id, 'to_handover');
        $unplaced = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Quote'], ['sort_order' => 9]);

        return [$pending, $shipped, $unplaced];
    }

    /** Pickup addresses hang off a country row; the Philippines, as the store is. */
    private function countryId(): int
    {
        return \App\Models\Country::firstOrCreate(
            ['iso2' => 'PH'],
            ['iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true, 'sort_order' => 1]
        )->id;
    }

    private function order(OrderStatus $status, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'phone' => '+639171234567',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St', 'shipping_city' => 'Cebu City', 'shipping_state' => 'Cebu', 'shipping_postcode' => '6000',
            'shipping_phone' => '+639171234567',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ], $overrides));
    }

    public function test_order_payloads_carry_the_booking_in_its_three_shapes(): void
    {
        [$pending, $shipped] = $this->statuses();
        $none = $this->order($pending);
        // The courier columns belong to the extension, not to Order's own
        // fillable list, so they are written the way the service writes them.
        $booked = $this->order($shipped);
        $booked->forceFill([
            'courier_provider' => 'quadx', 'courier_tracking_number' => 'QX-1', 'courier_status' => 'for_pickup', 'courier_booked_at' => now(),
        ])->save();
        $lbc = ShippingCourier::create(['store_id' => 1, 'name' => 'LBC Branch', 'is_active' => true, 'sort_order' => 1]);
        $manual = $this->order($shipped, ['shipping_courier_id' => $lbc->id, 'tracking_number' => 'LBC-9']);

        $this->api()->getJson('/api/v1/orders/' . $none->id)->assertOk()
            ->assertJsonPath('courier.provider', null)
            ->assertJsonPath('courier.manual', false)
            ->assertJsonPath('courier.tracking_number', null);

        $this->api()->getJson('/api/v1/orders/' . $booked->id)->assertOk()
            ->assertJsonPath('courier.provider', 'quadx')
            ->assertJsonPath('courier.tracking_number', 'QX-1')
            ->assertJsonPath('courier.status', 'for_pickup')
            ->assertJsonPath('courier.manual', false);

        $this->api()->getJson('/api/v1/orders/' . $manual->id)->assertOk()
            ->assertJsonPath('courier.provider', null)
            ->assertJsonPath('courier.manual', true)
            ->assertJsonPath('courier.manual_courier', 'LBC Branch')
            ->assertJsonPath('courier.tracking_number', 'LBC-9');

        // The list carries the same object, and updated_at beside created_at.
        $list = $this->api()->getJson('/api/v1/orders?per_page=50')->assertOk()->json('data');
        $byId = collect($list)->keyBy('id');
        $this->assertSame('quadx', $byId[$booked->id]['courier']['provider']);
        $this->assertTrue($byId[$manual->id]['courier']['manual']);
        $this->assertArrayHasKey('updated_at', $byId[$none->id]);
    }

    public function test_orders_can_be_listed_by_when_they_last_changed(): void
    {
        [$pending] = $this->statuses();
        $old = $this->order($pending);
        Order::whereKey($old->id)->update(['updated_at' => now()->subDays(3), 'created_at' => now()->subDays(3)]);
        $fresh = $this->order($pending);

        $ids = collect($this->api()->getJson('/api/v1/orders?updated_since=' . urlencode(now()->subDay()->toIso8601String()))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertContains($fresh->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_statuses_carry_their_queue_placement(): void
    {
        [$pending, $shipped, $unplaced] = $this->statuses();

        $rows = collect($this->api()->getJson('/api/v1/order-statuses')->assertOk()->json())->keyBy('id');

        $this->assertSame('to_pack', $rows[$pending->id]['placement']);
        $this->assertSame('to_handover', $rows[$shipped->id]['placement']);
        $this->assertNull($rows[$unplaced->id]['placement']);
    }

    public function test_shipping_couriers_list_the_stores_own_and_can_drop_the_retired(): void
    {
        ShippingCourier::create(['store_id' => 1, 'name' => 'LBC Branch', 'is_active' => true, 'sort_order' => 1]);
        ShippingCourier::create(['store_id' => 1, 'name' => 'Old courier', 'is_active' => false, 'sort_order' => 2]);

        $this->api()->getJson('/api/v1/shipping-couriers')->assertOk()->assertJsonCount(2, 'data');
        $this->api()->getJson('/api/v1/shipping-couriers?active_only=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'LBC Branch');
    }

    public function test_pickup_addresses_come_default_first_with_a_summary(): void
    {
        PickupAddress::create(['label' => 'Warehouse B', 'contact_name' => 'B', 'contact_phone' => '+639170000002', 'address_1' => '12 Depot Rd', 'city' => 'Pasig', 'state' => 'Metro Manila', 'postcode' => '1600', 'country_id' => $this->countryId()]);
        $main = PickupAddress::create(['label' => 'Main store', 'contact_name' => 'A', 'contact_phone' => '+639170000001', 'address_1' => '1 Shop St', 'city' => 'Makati', 'state' => 'Metro Manila', 'postcode' => '1200', 'country_id' => $this->countryId(), 'is_default' => true]);

        $this->api()->getJson('/api/v1/pickup-addresses')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $main->id)
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonPath('data.0.summary', '1 Shop St, Makati, Metro Manila 1200');
    }

    public function test_serviceability_names_each_courier_and_lists_what_a_booking_can_choose(): void
    {
        $this->enableQuadx();
        [$pending] = $this->statuses();
        $order = $this->order($pending);

        $json = $this->api()->getJson('/api/v1/orders/' . $order->id . '/serviceability')->assertOk()->json();

        $this->assertArrayHasKey('quadx', $json['couriers']);
        $quadx = $json['couriers']['quadx'];
        $this->assertSame('QuadX', $quadx['name']);
        $this->assertSame(['next_day', 'same_day_pickup'], array_column($quadx['service_types'], 'value'));
        $this->assertContains('box', array_column($quadx['parcel_options'], 'value'));
        $this->assertSame('cash', $quadx['shipping_payment_options'][0]['value']);
        // Cash on pickup by default, and the store's own configured
        // service and parcel.
        $this->assertSame('cash', $quadx['defaults']['shipping_payment']);
        $this->assertSame('next_day', $quadx['defaults']['service']);
        $this->assertSame('box', $quadx['defaults']['parcel']);
        // The verdict itself is still there.
        $this->assertArrayHasKey('status', $quadx);
    }

    public function test_a_booking_takes_every_field_the_admin_form_takes_and_defaults_who_pays_to_cash(): void
    {
        $this->enableQuadx();
        [$pending, $shipped] = $this->statuses();
        $address = PickupAddress::create(['label' => 'Main store', 'contact_name' => 'A', 'contact_phone' => '+639170000001', 'address_1' => '1 Shop St', 'city' => 'Makati', 'state' => 'Metro Manila', 'postcode' => '1200', 'country_id' => $this->countryId(), 'is_default' => true]);
        $order = $this->order($pending);

        $captured = [];
        $service = new class($captured) extends \Extensions\CourierFulfillment\FulfillmentService {
            public function __construct(private array &$captured) {}
            public function book(Order $order, array $opts): array
            {
                $this->captured[] = $opts;
                return ['success' => true, 'courier' => 'quadx', 'tracking_number' => 'QX-77', 'status' => 'for_pickup'];
            }
        };
        $this->app->instance(\Extensions\CourierFulfillment\FulfillmentService::class, $service);

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-courier', [
            'courier' => 'quadx', 'service' => 'same_day_pickup', 'parcel' => 'medium-pouch', 'insure' => true,
            'pickup_address_id' => $address->id, 'pickup_slot' => '',
        ])->assertOk()->assertJsonPath('tracking_number', 'QX-77');

        $this->assertCount(1, $captured);
        $opts = $captured[0];
        $this->assertSame('same_day_pickup', $opts['service']);
        $this->assertSame('medium-pouch', $opts['parcel']);
        $this->assertSame($address->id, $opts['pickup_address_id']);
        $this->assertTrue($opts['insure']);
        $this->assertSame('cash', $opts['shipping_payment'], 'who pays defaults to the courier\'s first option, cash on pickup for QuadX');
        $this->assertArrayNotHasKey('pickup_slot', $opts, 'a blank slot is left out, not sent as an empty string');
    }

    public function test_a_booking_sends_no_service_or_parcel_it_was_not_given(): void
    {
        $this->enableQuadx();
        [$pending] = $this->statuses();
        $order = $this->order($pending);

        $captured = [];
        $service = new class($captured) extends \Extensions\CourierFulfillment\FulfillmentService {
            public function __construct(private array &$captured) {}
            public function book(Order $order, array $opts): array
            {
                $this->captured[] = $opts;
                return ['success' => true, 'courier' => 'quadx', 'tracking_number' => 'QX-1', 'status' => 'for_pickup'];
            }
        };
        $this->app->instance(\Extensions\CourierFulfillment\FulfillmentService::class, $service);

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-courier', ['courier' => 'quadx'])->assertOk();

        // The courier applies its own configured defaults for these; the
        // API no longer writes next_day and box over them.
        $this->assertArrayNotHasKey('service', $captured[0]);
        $this->assertArrayNotHasKey('parcel', $captured[0]);
        $this->assertSame('cash', $captured[0]['shipping_payment']);
    }

    public function test_a_shipment_by_hand_is_recorded_and_moves_the_order_to_the_booked_status(): void
    {
        [$pending, $shipped] = $this->statuses();
        $lbc = ShippingCourier::create(['store_id' => 1, 'name' => 'LBC Branch', 'is_active' => true, 'sort_order' => 1]);
        $order = $this->order($pending);

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-manual', [
            'shipping_courier_id' => $lbc->id, 'tracking_number' => 'LBC-77', 'comment' => 'Rider took it at 3pm',
        ])->assertOk()
            ->assertJsonPath('courier.manual', true)
            ->assertJsonPath('courier.manual_courier', 'LBC Branch')
            ->assertJsonPath('courier.tracking_number', 'LBC-77')
            ->assertJsonPath('order_status', 'Order Being Shipped');

        $order->refresh();
        $this->assertSame($lbc->id, (int) $order->shipping_courier_id);
        $this->assertSame('LBC-77', $order->tracking_number);
        $this->assertSame($shipped->id, (int) $order->order_status_id);

        // The order payload now says so.
        $this->api()->getJson('/api/v1/orders/' . $order->id)->assertOk()
            ->assertJsonPath('courier.manual', true)
            ->assertJsonPath('courier.manual_courier', 'LBC Branch');

        // A retired courier is a wrong answer, not no answer.
        $retired = ShippingCourier::create(['store_id' => 1, 'name' => 'Gone', 'is_active' => false, 'sort_order' => 2]);
        $other = $this->order($pending);
        $this->api()->postJson('/api/v1/orders/' . $other->id . '/book-manual', ['shipping_courier_id' => $retired->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'courier_unavailable');
    }

    public function test_clearing_a_shipment_by_hand_returns_the_order_to_pack(): void
    {
        [$pending, $shipped] = $this->statuses();
        $lbc = ShippingCourier::create(['store_id' => 1, 'name' => 'LBC Branch', 'is_active' => true, 'sort_order' => 1]);
        $order = $this->order($pending);
        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-manual', ['shipping_courier_id' => $lbc->id, 'tracking_number' => 'LBC-77'])->assertOk();

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/clear-manual')->assertOk()->assertJsonPath('success', true);

        $order->refresh();
        $this->assertNull($order->shipping_courier_id);
        $this->assertNull($order->tracking_number);
        $this->assertSame($pending->id, (int) $order->order_status_id);

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/clear-manual')->assertStatus(422)->assertJsonPath('code', 'not_manual');
    }

    public function test_the_erp_token_guards_every_new_endpoint(): void
    {
        [$pending] = $this->statuses();
        $order = $this->order($pending);

        $this->getJson('/api/v1/shipping-couriers')->assertUnauthorized();
        $this->getJson('/api/v1/pickup-addresses')->assertUnauthorized();
        $this->postJson('/api/v1/orders/' . $order->id . '/book-manual', [])->assertUnauthorized();
        $this->postJson('/api/v1/orders/' . $order->id . '/clear-manual')->assertUnauthorized();
    }
}
