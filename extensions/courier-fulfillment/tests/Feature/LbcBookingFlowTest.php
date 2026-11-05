<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/LbcBookingFixtures.php';

use App\Models\OrderHistory;
use App\Models\Product;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcFulfillment;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcTrackingStatus;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Models\TrackingNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * An LBC booking from the queue's Book button to the stored shipment, with
 * every LBC answer faked: the request LBC receives, what the order keeps,
 * and what each of LBC's three answers does to the tracking number.
 */
class LbcBookingFlowTest extends TestCase
{
    use LbcBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLbcBooking();
        $this->signIn();
    }

    public function test_lbc_is_enabled_once_every_setting_is_present(): void
    {
        $lbc = new LbcFulfillment();

        $this->assertSame([], $lbc->configurationIssues());
        $this->assertTrue($lbc->isEnabled());
        $this->assertContains('lbc', array_map(fn ($c) => $c->getKey(), CourierManager::enabled()));
    }

    public function test_a_prepaid_booking_sends_one_request_and_stores_the_shipment(): void
    {
        $this->fakeLbc();
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())
            ->assertRedirect()
            ->assertSessionHas('success', 'Booked, tracking ' . self::SERIES_FIRST . '.');

        $fields = $this->sentPickupFields();
        $this->assertSame((string) self::SERIES_FIRST, $fields['TrackingNo']);
        $this->assertSame('REGULAR', $fields['ReferenceNoTwo']);
        $this->assertSame('16', $fields['PKG']);
        $this->assertSame('1.2', $fields['ACTWTkgs']);
        $this->assertSame('0', $fields['CODAmount']);
        $this->assertSame('0', $fields['DeclaredValue'], 'Uninsured prepaid parcels declare 0.');
        $this->assertSame('11/05/2026 10:00:00', $fields['PreferredDate']);
        $this->assertSame((string) $order->id, $fields['ReferenceNoOne']);

        $fresh = $this->freshOrder($order);
        $this->assertSame('lbc', $fresh->courier_provider);
        $this->assertSame((string) self::SERIES_FIRST, $fresh->courier_tracking_number);
        $this->assertSame(LbcTrackingStatus::BOOKED, $fresh->courier_status);
        $this->assertSame($this->handover->id, (int) $fresh->order_status_id);
        $this->assertSame(LbcFulfillment::PUBLIC_TRACKER_URL . self::SERIES_FIRST, $fresh->getRawOriginal('tracking_link'));
        $this->assertSame('LBC', $fresh->shipping_courier_name);

        $meta = $this->meta($order);
        $this->assertSame(self::FIRST_PICKUP, $meta['pickup_date']);
        $this->assertFalse($meta['insured']);
        $this->assertSame((string) self::SERIES_FIRST, $meta['pickup_request']['TrackingNo']);
        $this->assertArrayNotHasKey('ShipperAccountNo', $meta['pickup_request'], 'The account number is not kept on the order.');

        $this->assertSame(TrackingNumber::SPENT, TrackingNumber::where('number', self::SERIES_FIRST)->value('status'));
    }

    public function test_an_insured_prepaid_parcel_declares_the_order_total_with_the_floor(): void
    {
        $this->fakeLbc();
        $order = $this->bookable(['total' => 1250, 'subtotal' => 1150, 'shipping_cost' => 100]);
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['insure' => '1']));
        $this->assertSame('1250', $this->sentPickupFields()['DeclaredValue']);
    }

    public function test_an_insured_value_below_the_floor_is_raised_to_it(): void
    {
        $this->fakeLbc();
        $order = $this->bookable(['total' => 300, 'subtotal' => 300]);
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['insure' => '1']));
        $this->assertSame('500', $this->sentPickupFields()['DeclaredValue']);
    }

    public function test_cod_collects_the_order_total_and_is_always_insured(): void
    {
        $this->fakeLbc();
        $order = $this->codOrder(['total' => 1350, 'subtotal' => 1200, 'shipping_cost' => 150]);

        // No insure field at all: COD insures regardless.
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())->assertSessionHas('success');

        $fields = $this->sentPickupFields();
        $this->assertSame('1350', $fields['CODAmount'], 'The rider collects the total including the shipping the buyer paid.');
        $this->assertSame('1350', $fields['DeclaredValue']);

        $meta = $this->meta($order);
        $this->assertTrue($meta['insured']);
        $this->assertTrue($meta['cod']);
        $this->assertSame('1350', $meta['pickup_request']['CODAmount']);
    }

    public function test_cod_insurance_cannot_be_turned_off(): void
    {
        $this->fakeLbc();
        $order = $this->codOrder(['total' => 900, 'subtotal' => 900]);
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['insure' => '0']));
        $this->assertSame('900', $this->sentPickupFields()['DeclaredValue']);
    }

    public function test_a_definite_rejection_releases_the_number_and_books_nothing(): void
    {
        // Refused the first time, saved the second.
        $calls = 0;
        $this->fakeLbc(function (Request $request) use (&$calls) {
            return ++$calls === 1
                ? Http::response(self::pickupRejected('131', 'PKG is required'))
                : Http::response(self::pickupAccepted((string) simplexml_load_string($request->body())->TrackingNo));
        });
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'LBC refused the booking')
                && str_contains($e, 'code 131')
                && str_contains($e, (string) self::SERIES_FIRST . ' was not used'));

        $this->assertNull($this->freshOrder($order)->courier_tracking_number);
        $this->assertSame($this->toPack->id, (int) $this->freshOrder($order)->order_status_id);
        $this->assertSame(TrackingNumber::RELEASED, TrackingNumber::where('number', self::SERIES_FIRST)->value('status'));

        // The released number is the next one taken.
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())->assertSessionHas('success');
        $this->assertSame((string) self::SERIES_FIRST, $this->freshOrder($order)->courier_tracking_number);
    }

    public function test_an_unknown_answer_spends_the_number_and_books_it_as_unconfirmed(): void
    {
        $this->fakeLbc(fn () => Http::response('Gateway timeout', 504));
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())
            ->assertSessionHas('warning', fn (string $w) => str_contains($w, 'LBC did not confirm pickup request ' . self::SERIES_FIRST));

        $fresh = $this->freshOrder($order);
        $this->assertSame(LbcTrackingStatus::UNCONFIRMED, $fresh->courier_status);
        $this->assertSame(TrackingNumber::SPENT, TrackingNumber::where('number', self::SERIES_FIRST)->value('status'));
        $this->assertTrue(OrderHistory::where('order_id', $order->id)->where('comment', 'like', '%did not confirm pickup request%')->exists());

        // Never retried, and a second Book is answered by the booking that exists.
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm());
        $this->assertCount(1, Http::recorded(fn (Request $r) => self::isPickupRequest($r)));
    }

    public function test_an_unserviceable_address_is_refused_before_a_number_is_taken(): void
    {
        $this->fakeLbc(deliveryStatus: 'Not Serviceable');
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'LBC does not serve the delivery address'));

        $this->assertCount(0, Http::recorded(fn (Request $r) => self::isPickupRequest($r)));
        $this->assertSame(0, TrackingNumber::count());
    }

    public function test_drop_off_is_refused_while_it_is_not_offered(): void
    {
        $this->fakeLbc();
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['service' => 'DROPOFF']))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'Drop off at an LBC branch is not offered yet'));

        Http::assertNothingSent();
        $this->assertSame(0, TrackingNumber::count());
    }

    public function test_an_own_box_without_dimensions_is_refused_and_lbc_packaging_may_omit_them(): void
    {
        $this->fakeLbc();
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['length_cm' => null, 'width_cm' => null, 'height_cm' => null]))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'needs its length, width and height'));
        Http::assertNothingSent();

        // A Kilobox Small without a size is sent with 0, for LBC to accept or refuse.
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['parcel' => '6', 'length_cm' => null, 'width_cm' => null, 'height_cm' => null]))
            ->assertSessionHas('success');
        $fields = $this->sentPickupFields();
        $this->assertSame(['6', '0', '0', '0', '0', '1.2'], [$fields['PKG'], $fields['LengthCM'], $fields['WidthCM'], $fields['HeightCM'], $fields['VolWTcbm'], $fields['ChargeableWT']]);
    }

    public function test_a_sunday_or_past_pickup_date_is_refused(): void
    {
        $this->fakeLbc();
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['pickup_slot' => '2026-11-08']))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'Sundays'));
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['pickup_slot' => '2026-11-04']))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'Nov 5, 2026 or later'));
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['pickup_slot' => 'tomorrow']))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'must be a date'));

        Http::assertNothingSent();
    }

    public function test_no_pickup_date_means_the_next_business_day(): void
    {
        // Saturday: the next business day skips Sunday.
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-07 15:00:00', 'Asia/Manila'));
        $this->fakeLbc();
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['pickup_slot' => null]))->assertSessionHas('success');
        $this->assertSame('11/09/2026 10:00:00', $this->sentPickupFields()['PreferredDate']);
    }

    public function test_weight_prefills_from_the_products_and_must_be_given_when_unknown(): void
    {
        $this->fakeLbc();
        $order = $this->bookable();

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['weight_kg' => null]))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'Enter the parcel weight'));
        Http::assertNothingSent();

        $product = Product::create(['store_id' => 1, 'name' => 'Demo Item', 'slug' => 'demo-item', 'price' => 500, 'weight' => 750, 'weight_unit' => 'g']);
        $order->products()->update(['product_id' => $product->id, 'quantity' => 2]);

        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm(['weight_kg' => null]))->assertSessionHas('success');
        $this->assertSame('1.5', $this->sentPickupFields()['ACTWTkgs']);
    }

    public function test_the_queue_offers_lbc_with_its_own_fields(): void
    {
        $this->bookable();

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('data-courier-card="lbc"', false)
            ->assertSee('Rate estimate')
            ->assertSee('Pickup date')
            ->assertSee('Size (cm)')
            ->assertSee('Rider pickup')
            ->assertSee('Drop off at an LBC branch')
            ->assertSee('Kilobox Mini')
            // The courier options reach Alpine through @js, which escapes quotes.
            ->assertSee('nextTrackingNumber\\u0022:\\u0022' . self::SERIES_FIRST, false)
            ->assertSee('pickupDateMin\\u0022:\\u0022' . self::FIRST_PICKUP, false)
            ->assertSee('insureLockedForCod\\u0022:true', false)
            ->assertSee('modeLabel\\u0022:\\u0022Live', false);

        Http::assertNothingSent();
    }
}
