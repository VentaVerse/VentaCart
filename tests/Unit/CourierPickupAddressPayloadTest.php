<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The regression that matters in the pickup-address move: a booking that
 * worked an hour ago must still work.
 *
 * Both couriers used to build their origin block from
 * settings(group=shipping, key=pickup_*). They now build it from a
 * courier_fulfillment_pickup_addresses record. These tests pin the exact
 * block each courier sends, as GOLDEN VALUES copied from what the settings
 * version produced for the store's real origin (Quezon City / Metro Manila /
 * San Bartolome / 1116, the row the one-off import command creates), so a
 * later change to either adapter's mapping cannot quietly alter the payload.
 *
 * Http::preventStrayRequests() throughout: QuadX's credentials are live
 * production, and nothing in this file may ever reach a real courier.
 */
class CourierPickupAddressPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();

        Http::preventStrayRequests();

        Country::create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]);
    }

    /**
     * The store's real origin, exactly as the retired pickup_* settings held
     * it and exactly as `fulfillment:import-pickup-address` writes it.
     */
    private function storeOrigin(array $overrides = []): PickupAddress
    {
        return PickupAddress::create(array_merge([
            'label'         => 'Store pickup',
            'contact_name'  => 'Paolo',
            'contact_phone' => '09998889999',
            'address_1'     => 'Street',
            'barangay'      => 'San Bartolome',
            'district'      => null,
            'city'          => 'Quezon City',
            'state'         => 'Metro Manila',
            'postcode'      => '1116',
            'country_id'    => Country::where('iso2', 'PH')->value('id'),
        ], $overrides));
    }

    private function makeOrder(): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);

        return Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'phone' => '+639171234567',
            'shipping_first_name' => 'Jane', 'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_barangay' => 'Lahug',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'shipping_postcode' => '6000',
            'shipping_phone' => '+639171234567',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000, 'shipping_cost' => 100, 'total' => 1100,
        ]);
    }

    // ------------------------------------------------------------------
    // SPX
    // ------------------------------------------------------------------

    private function configureSpx(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_mode', 'value' => 'uat']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => Crypt::encryptString('fake-user-secret-not-real')]);
    }

    /** The exact sender_info the settings version built for this origin. */
    private const SPX_GOLDEN_SENDER = [
        'sender_state'          => 'Metro Manila',
        'sender_city'           => 'Quezon City',
        'sender_district'       => 'Quezon City',
        'sender_street'         => 'San Bartolome',
        'sender_post_code'      => '1116',
        'sender_name'           => 'Paolo',
        'sender_phone'          => '09998889999',
        'sender_detail_address' => 'Street',
    ];

    /** Reads the sender_info SPX's fee check would send, without booking anything. */
    private function spxSenderInfo(array $opts = []): array
    {
        Http::fake(['*batch_check_order*' => Http::response([
            'ret_code' => 0, 'message' => 'success.',
            'data' => ['orders' => [['order_id' => '1', 'estimated_shipping_fee' => 23.2]]],
        ], 200)]);

        $courier = new SpxFulfillment(sleeper: function (int $ms): void {});
        $courier->estimateFee($this->makeOrder(), array_merge(['parcel' => 'small', 'shipping_payment' => 'sender_pay'], $opts));

        $sender = null;
        Http::assertSent(function ($request) use (&$sender) {
            if (!str_contains($request->url(), 'batch_check_order')) {
                return false;
            }
            $sender = $request->data()['orders'][0]['sender_info'];

            return true;
        });

        return $sender ?? [];
    }

    public function test_spx_builds_the_same_sender_block_from_the_address_record(): void
    {
        $this->configureSpx();
        $this->storeOrigin();

        $this->assertSame(self::SPX_GOLDEN_SENDER, $this->spxSenderInfo());
    }

    public function test_spx_books_from_the_address_chosen_at_booking_time(): void
    {
        $this->configureSpx();
        $this->storeOrigin();
        $second = $this->storeOrigin([
            'label'         => 'Cebu depot',
            'contact_name'  => 'Depot Team',
            'contact_phone' => '09171112222',
            'address_1'     => '456 Warehouse Ave',
            'barangay'      => 'Lahug',
            'city'          => 'Cebu City',
            'state'         => 'Cebu',
            'postcode'      => '6000',
        ]);

        // The default is untouched by adding a second address.
        $this->assertFalse($second->fresh()->is_default);

        $sender = $this->spxSenderInfo(['pickup_address_id' => $second->id]);

        $this->assertSame('Cebu', $sender['sender_state']);
        $this->assertSame('Cebu City', $sender['sender_city']);
        $this->assertSame('Lahug', $sender['sender_street']);
        $this->assertSame('6000', $sender['sender_post_code']);
        $this->assertSame('Depot Team', $sender['sender_name']);
        $this->assertSame('09171112222', $sender['sender_phone']);
        $this->assertSame('456 Warehouse Ave', $sender['sender_detail_address']);
    }

    public function test_spx_uses_the_default_when_no_address_is_chosen(): void
    {
        $this->configureSpx();
        $this->storeOrigin();
        $this->storeOrigin(['label' => 'Cebu depot', 'city' => 'Cebu City', 'state' => 'Cebu']);

        $this->assertSame(self::SPX_GOLDEN_SENDER, $this->spxSenderInfo());
    }

    public function test_spx_refuses_to_book_with_no_pickup_address_on_file(): void
    {
        $this->configureSpx();
        Http::fake();

        $result = (new SpxFulfillment(sleeper: function (int $ms): void {}))
            ->createShipment($this->makeOrder(), ['parcel' => 'small', 'shipping_payment' => 'sender_pay']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('pickup address', $result['error']);
        Http::assertNothingSent();
    }

    /** An address that DOES carry a district sends it, rather than repeating the city. */
    public function test_spx_sends_a_real_district_when_the_address_has_one(): void
    {
        $this->configureSpx();
        $this->storeOrigin(['city' => 'Manila', 'district' => 'Binondo']);

        $this->assertSame('Binondo', $this->spxSenderInfo()['sender_district']);
    }

    // ------------------------------------------------------------------
    // QuadX
    // ------------------------------------------------------------------

    private function configureQuadX(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('fake-api-key-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('fake-secret-key-not-real')]);
    }

    /** The exact pickup_address block the settings version built for this origin. */
    private const QUADX_GOLDEN_PICKUP = [
        'name'          => 'Paolo',
        'mobile_number' => '09998889999',
        'line_1'        => 'Street',
        'district'      => 'San Bartolome',
        'city'          => 'Quezon City',
        'state'         => 'Metro Manila',
        'postal_code'   => '1116',
        'country'       => 'PH',
    ];

    private function quadxPickupAddress(array $opts = []): array
    {
        Http::fake(['*' => Http::response([
            'id' => 'qx-1', 'tracking_number' => 'VY00-TEST-0001', 'status' => 'for_pickup', 'reference_id' => '1',
        ], 200)]);

        (new QuadXFulfillment())->createShipment(
            $this->makeOrder(),
            array_merge(['service' => 'next_day', 'parcel' => 'box', 'shipping_payment' => 'net_off'], $opts)
        );

        $pickup = null;
        Http::assertSent(function ($request) use (&$pickup) {
            if (!str_contains($request->url(), '/orders')) {
                return false;
            }
            $pickup = $request->data()['pickup_address'];

            return true;
        });

        return $pickup ?? [];
    }

    public function test_quadx_builds_the_same_pickup_block_from_the_address_record(): void
    {
        $this->configureQuadX();
        $this->storeOrigin();

        $this->assertSame(self::QUADX_GOLDEN_PICKUP, $this->quadxPickupAddress());
    }

    public function test_quadx_books_from_the_address_chosen_at_booking_time(): void
    {
        $this->configureQuadX();
        $this->storeOrigin();
        $second = $this->storeOrigin([
            'label'         => 'Cebu depot',
            'contact_name'  => 'Depot Team',
            'contact_phone' => '09171112222',
            'address_1'     => '456 Warehouse Ave',
            'barangay'      => 'Lahug',
            'city'          => 'Cebu City',
            'state'         => 'Cebu',
            'postcode'      => '6000',
        ]);

        $pickup = $this->quadxPickupAddress(['pickup_address_id' => $second->id]);

        $this->assertSame('Depot Team', $pickup['name']);
        $this->assertSame('09171112222', $pickup['mobile_number']);
        $this->assertSame('456 Warehouse Ave', $pickup['line_1']);
        $this->assertSame('Lahug', $pickup['district']);
        $this->assertSame('Cebu City', $pickup['city']);
        $this->assertSame('Cebu', $pickup['state']);
        $this->assertSame('6000', $pickup['postal_code']);
        $this->assertSame('PH', $pickup['country']);
    }

    public function test_quadx_uses_the_default_when_no_address_is_chosen(): void
    {
        $this->configureQuadX();
        $this->storeOrigin();
        $this->storeOrigin(['label' => 'Cebu depot', 'city' => 'Cebu City', 'state' => 'Cebu']);

        $this->assertSame(self::QUADX_GOLDEN_PICKUP, $this->quadxPickupAddress());
    }

    /** An address carrying a district sends it, exactly as deliveryAddress() prefers shipping_district. */
    public function test_quadx_prefers_a_real_district_over_the_barangay(): void
    {
        $this->configureQuadX();
        $this->storeOrigin(['city' => 'Manila', 'district' => 'Binondo']);

        $this->assertSame('Binondo', $this->quadxPickupAddress()['district']);
    }

    // ------------------------------------------------------------------
    // The origin is recorded on the shipment.
    // ------------------------------------------------------------------

    public function test_a_booking_records_which_address_it_left_from(): void
    {
        $this->configureQuadX();
        $this->storeOrigin();
        $second = $this->storeOrigin(['label' => 'Cebu depot', 'city' => 'Cebu City', 'state' => 'Cebu', 'postcode' => '6000']);

        $status = OrderStatus::create(['store_id' => 1, 'name' => 'To Pack', 'sort_order' => 2]);
        \Illuminate\Support\Facades\DB::table('courier_fulfillment_status_placements')->insert([
            'order_status_id' => $status->id, 'placement' => 'to_pack',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $handover = OrderStatus::create(['store_id' => 1, 'name' => 'Order Being Shipped', 'sort_order' => 3]);
        \Illuminate\Support\Facades\DB::table('courier_fulfillment_status_placements')->insert([
            'order_status_id' => $handover->id, 'placement' => 'to_handover',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \Tests\Support\FulfillmentSettings::selectFor('to_handover', (int) $handover->id);

        $order = $this->makeOrder();
        $order->forceFill(['order_status_id' => $status->id])->save();

        Http::fake(['*' => Http::response([
            'id' => 'qx-1', 'tracking_number' => 'VY00-TEST-0002', 'status' => 'for_pickup', 'reference_id' => (string) $order->id,
        ], 200)]);

        $result = app(\Extensions\CourierFulfillment\FulfillmentService::class)->book($order, [
            'courier' => 'quadx', 'service' => 'next_day', 'parcel' => 'box',
            'shipping_payment' => 'net_off', 'pickup_address_id' => $second->id,
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');

        $meta = json_decode($order->fresh()->courier_meta, true);
        $this->assertSame($second->id, $meta['pickup_address']['id']);
        $this->assertSame('Cebu depot', $meta['pickup_address']['label']);
        $this->assertStringContainsString('Cebu City', $meta['pickup_address']['summary']);
    }
}
