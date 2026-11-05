<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/LbcBookingFixtures.php';

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcClient;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The ERP's api/v1 fulfillment calls for an LBC order, with the same
 * payload shapes QuadX answers in, plus who may reach the LBC screens and
 * actions at all.
 */
class LbcErpAndAccessTest extends TestCase
{
    use LbcBookingFixtures;
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n% demo waybill\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLbcBooking();
        app(ExtensionManager::class)->install('erp-sync');
        app(ExtensionManager::class)->bootEnabled();
        $this->apiKey('erp-test-token');
        Storage::fake('local');
    }

    private function api()
    {
        return $this->withToken('erp-test-token');
    }

    private function fakeAll(): void
    {
        $this->fakeLbc(null, [
            '*' . LbcClient::WAYBILL_PATH => Http::response(['Rendered_Byte' => base64_encode(self::PDF)]),
            '*' . LbcClient::TRACK_PATH => Http::response(
                '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><LBCTrackAndTraceResponse xmlns="http://tempuri.org/"><LBCTrackAndTraceResult><TrackingStatus>'
                . '<StatusCode>0001</StatusCode><StatusMessage>SUCCESS!</StatusMessage><TrackingHistory><TrackingHistory><StatusId>0</StatusId>'
                . '<DatePosted>11/05/2026</DatePosted><StatusandLocation>PICKED UP BY LBC</StatusandLocation><DatePostedTime>02:10:00 PM</DatePostedTime>'
                . '</TrackingHistory></TrackingHistory></TrackingStatus></LBCTrackAndTraceResult></LBCTrackAndTraceResponse></soap:Body></soap:Envelope>'
            ),
        ]);
    }

    public function test_the_erp_books_an_lbc_order_with_the_shared_payload(): void
    {
        $this->fakeAll();
        $order = $this->bookable();

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-courier', [
            'courier' => 'lbc',
            'parcel' => '6',
            'weight_kg' => 0.8,
            'insure' => true,
            'pickup_slot' => self::FIRST_PICKUP,
        ])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'already_booked' => false,
                'order_id' => $order->id,
                'courier' => 'lbc',
                'tracking_number' => (string) self::SERIES_FIRST,
                'status' => 'booked',
            ]);

        $fields = $this->sentPickupFields();
        $this->assertSame(['6', '0.8', '500', 'REGULAR'], [$fields['PKG'], $fields['ACTWTkgs'], $fields['DeclaredValue'], $fields['ReferenceNoTwo']]);

        // A retry answers with the booking that exists and sends nothing.
        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-courier', ['courier' => 'lbc', 'parcel' => '6', 'weight_kg' => 0.8])
            ->assertOk()->assertJsonPath('already_booked', true);
        $this->assertCount(1, Http::recorded(fn (Request $r) => self::isPickupRequest($r)));
    }

    public function test_the_erp_gets_a_clear_refusal_without_a_parcel(): void
    {
        $this->fakeAll();
        $order = $this->bookable();

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-courier', ['courier' => 'lbc', 'weight_kg' => 1])
            ->assertStatus(422)
            ->assertJsonPath('code', 'booking_failed')
            ->assertJsonPath('error', 'Choose an LBC parcel.');
        Http::assertNothingSent();
    }

    public function test_the_erp_reads_tracking_label_and_cancels(): void
    {
        $this->fakeAll();
        $order = $this->bookable();
        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-courier', ['courier' => 'lbc', 'parcel' => '16', 'weight_kg' => 1, 'length_cm' => 20, 'width_cm' => 15, 'height_cm' => 10])
            ->assertOk();

        $this->api()->getJson('/api/v1/orders/' . $order->id . '/tracking')
            ->assertOk()
            ->assertJsonPath('courier', 'lbc')
            ->assertJsonPath('tracking_number', (string) self::SERIES_FIRST)
            ->assertJsonPath('status', 'picked_up')
            ->assertJsonPath('timeline.0.status', 'picked_up')
            ->assertJsonPath('links', []);

        $this->api()->get('/api/v1/orders/' . $order->id . '/label')
            ->assertOk()
            ->assertDownload('label-order-' . $order->id . '.pdf');

        // LBC has the parcel now: a local reset would put a moving parcel
        // back in To Pack, so the cancel is refused.
        $this->api()->postJson('/api/v1/orders/' . $order->id . '/cancel-booking')
            ->assertStatus(422)
            ->assertJsonPath('error', fn (string $e) => str_contains($e, 'LBC already has this parcel (Picked up by LBC)'));
        $this->assertSame((string) self::SERIES_FIRST, $this->freshOrder($order)->courier_tracking_number);
    }

    public function test_the_erp_cancels_a_booking_still_waiting_for_its_rider_locally(): void
    {
        $this->fakeAll();
        $order = $this->bookable();
        $this->api()->postJson('/api/v1/orders/' . $order->id . '/book-courier', ['courier' => 'lbc', 'parcel' => '6', 'weight_kg' => 1])->assertOk();
        $sent = Http::recorded()->count();

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/cancel-booking')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tracking_number', (string) self::SERIES_FIRST)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'LBC has no cancel API. Call LBC to void this booking.'));

        $this->assertSame($sent, Http::recorded()->count());
        $this->assertNull($this->freshOrder($order)->courier_tracking_number);
    }

    public function test_the_erp_sees_lbc_choices_estimate_and_pickup_dates(): void
    {
        $this->fakeAll();
        $order = $this->bookable();

        $this->api()->getJson('/api/v1/orders/' . $order->id . '/serviceability')
            ->assertOk()
            ->assertJsonPath('couriers.lbc.status', 'serviceable')
            ->assertJsonPath('couriers.lbc.name', 'LBC')
            ->assertJsonPath('couriers.lbc.service_types.0', ['value' => 'REGULAR', 'label' => 'Rider pickup'])
            ->assertJsonPath('couriers.lbc.service_types.1.value', 'DROPOFF')
            ->assertJsonPath('couriers.lbc.service_types.1.disabled', true)
            ->assertJsonPath('couriers.lbc.parcel_options.0', ['value' => '16', 'label' => 'Own box'])
            ->assertJsonPath('couriers.lbc.shipping_payment_options', [['value' => 'account', 'label' => 'Charged to my LBC account']])
            ->assertJsonPath('couriers.lbc.defaults.service', 'REGULAR')
            ->assertJsonPath('couriers.lbc.defaults.shipping_payment', 'account');

        $this->api()->postJson('/api/v1/orders/' . $order->id . '/estimate', ['courier' => 'lbc'])
            ->assertOk()
            ->assertJsonPath('amount', 250)
            ->assertJsonPath('label', '₱250.00 (LBC estimate)')
            ->assertJsonPath('currency', 'PHP');

        $slots = $this->api()->getJson('/api/v1/orders/' . $order->id . '/pickup-slots?courier=lbc')
            ->assertOk()
            ->assertJsonPath('courier', 'lbc')
            ->json('slots');
        $this->assertSame(['value' => '2026-11-05', 'label' => 'Thu, Nov 5'], $slots[0]);
        $this->assertNotContains('2026-11-08', array_column($slots, 'value'), 'No Sunday is offered.');

        $this->assertCount(0, Http::recorded(fn (Request $r) => self::isPickupRequest($r)), 'Asking books nothing.');
    }

    public function test_the_erp_needs_its_token(): void
    {
        $order = $this->bookable();
        $this->postJson('/api/v1/orders/' . $order->id . '/book-courier', ['courier' => 'lbc'])->assertUnauthorized();
        $this->withToken('wrong-token')->postJson('/api/v1/orders/' . $order->id . '/book-courier', ['courier' => 'lbc'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    // --- Admin access --------------------------------------------------------------

    private function adminWith(string ...$slugs): User
    {
        $role = Role::firstOrCreate(['slug' => 'lbc-limited'], ['name' => 'Limited']);
        foreach ($slugs as $slug) {
            $role->permissions()->syncWithoutDetaching([
                Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => strtok($slug, '.')])->id,
            ]);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_viewing_orders_does_not_allow_booking_or_cancelling(): void
    {
        $this->fakeAll();
        $order = $this->bookable();
        $this->signIn($this->adminWith('orders.view'));

        $this->get($this->queueUrl())->assertOk();
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())->assertForbidden();
        $this->post($this->queueUrl('/' . $order->id . '/cancel'))->assertForbidden();
        $this->post($this->queueUrl('/' . $order->id . '/estimate'), ['courier' => 'lbc'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_an_admin_without_orders_cannot_open_the_queue_or_the_lbc_settings(): void
    {
        $order = $this->bookable();
        $this->signIn($this->adminWith('products.view'));

        $this->get($this->queueUrl())->assertForbidden();
        $this->get($this->queueUrl('/' . $order->id . '/serviceability'))->assertForbidden();
        $this->get($this->queueUrl('/lbc'))->assertForbidden();
        $this->post($this->queueUrl('/lbc'), ['settings' => ['lbc_client_code' => 'X']])->assertForbidden();
        $this->assertSame('DEMO', \App\Models\Setting::where('group', 'courier')->where('key', 'lbc_client_code')->value('value'));
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $order = $this->bookable();
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())->assertRedirect();
        $this->assertNull($this->freshOrder($order)->courier_tracking_number);
        Http::assertNothingSent();
    }

    public function test_the_settings_page_shows_what_is_left_of_the_series(): void
    {
        $this->signIn();

        $this->get($this->queueUrl('/lbc'))
            ->assertOk()
            ->assertSee('data-series-status', false)
            ->assertSee('10 left of 10')
            ->assertSee('Next')
            ->assertSee((string) self::SERIES_FIRST)
            ->assertDontSee('Refresh from LBC')
            ->assertSee('Order status mapping')
            ->assertSee('Pickup requested');

        // Nine used: two left of ten is past the warning line.
        $service = app(\Extensions\CourierFulfillment\TrackingNumbers\TrackingNumberSeriesService::class);
        foreach (range(1, 8) as $ignored) {
            $service->spend($service->reserve('lbc', self::SERIES_FIRST, self::SERIES_LAST), 'Accepted');
        }

        $this->get($this->queueUrl('/lbc'))
            ->assertSee('2 left of 10')
            ->assertSee('Running low. Request a new block from LBC.');

        $this->assertSame(['remaining' => 2, 'next' => self::SERIES_FIRST + 8], array_intersect_key(
            (new LbcFulfillment())->trackingNumberSeriesStatus(),
            ['remaining' => 0, 'next' => 0]
        ));
    }
}
