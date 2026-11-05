<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/LbcFixtures.php';

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcClient;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FulfillmentSettings;

/**
 * An LBC store ready to book from the queue: LBC switched on with complete
 * settings, a To Pack and a To Handover status, a pickup address, a signed
 * in admin, and the clock on a Wednesday morning in Manila. Every LBC
 * answer is faked and stray requests are refused.
 */
trait LbcBookingFixtures
{
    use LbcFixtures;

    protected OrderStatus $toPack;

    protected OrderStatus $handover;

    /** Wednesday; the first pickup date is the Thursday after. */
    protected const NOW = '2026-11-04 09:00:00';

    protected const FIRST_PICKUP = '2026-11-05';

    protected function setUpLbcBooking(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::NOW, 'Asia/Manila')->setTimezone(config('app.timezone')));

        $this->setUpLbc();
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'lbc_enabled'], ['value' => '1']);

        $this->toPack = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Processing'], ['sort_order' => 2]);
        PlacementMap::put($this->toPack->id, 'to_pack');
        FulfillmentSettings::selectFor('to_pack', (int) $this->toPack->id);

        $this->handover = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Order Being Shipped'], ['sort_order' => 4]);
        PlacementMap::put($this->handover->id, 'to_handover');
        FulfillmentSettings::selectFor('to_handover', (int) $this->handover->id);

        $this->pickupAddress();
    }

    protected function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    protected function bookable(array $overrides = []): Order
    {
        return $this->order(array_merge(['order_status_id' => $this->toPack->id], $overrides));
    }

    /** Marks the order's payment method as COD through the store's own mapping. */
    protected function codOrder(array $overrides = []): Order
    {
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'cod_payment_methods'], ['value' => json_encode(['cod'])]);

        return $this->bookable(array_merge(['payment_method' => 'cod', 'payment_method_title' => 'Cash on Delivery'], $overrides));
    }

    protected function queueUrl(string $suffix = ''): string
    {
        return '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment' . $suffix;
    }

    /** What the booking form posts for LBC, by default a box of 1.2 kg on the first pickup date. */
    protected function bookingForm(array $overrides = []): array
    {
        return array_merge([
            'courier' => 'lbc',
            'service' => 'REGULAR',
            'parcel' => '16',
            'shipping_payment' => 'account',
            'weight_kg' => '1.2',
            'length_cm' => '20',
            'width_cm' => '15',
            'height_cm' => '10',
            'pickup_slot' => self::FIRST_PICKUP,
        ], $overrides);
    }

    /** LBC's address answers plus a pickup endpoint that saves and echoes the number. */
    protected function fakeLbc(?callable $pickup = null, array $more = [], string $deliveryStatus = 'Serviceable'): void
    {
        $this->fakeLookups($deliveryStatus, 'METRO MANILA AREA', array_merge([
            '*' . LbcClient::PICKUP_PATH => $pickup ?? fn (Request $request) => Http::response(
                self::pickupAccepted((string) simplexml_load_string($request->body())->TrackingNo)
            ),
        ], $more));
    }

    protected static function isPickupRequest(Request $request): bool
    {
        return str_contains($request->url(), LbcClient::PICKUP_PATH);
    }

    /** The one pickup request sent, as its fields. */
    protected function sentPickupFields(): array
    {
        $sent = Http::recorded(fn (Request $request) => self::isPickupRequest($request))->values();
        $this->assertCount(1, $sent, 'Exactly one pickup request goes to LBC.');

        $xml = simplexml_load_string($sent[0][0]->body());
        $fields = [];
        foreach ($xml->children() as $node) {
            $fields[$node->getName()] = (string) $node;
        }

        return $fields;
    }

    protected function freshOrder(Order $order): Order
    {
        return Order::findOrFail($order->id);
    }

    protected function meta(Order $order): array
    {
        return json_decode((string) $this->freshOrder($order)->courier_meta, true) ?: [];
    }
}
