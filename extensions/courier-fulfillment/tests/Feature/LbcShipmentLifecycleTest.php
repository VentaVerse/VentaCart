<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/LbcBookingFixtures.php';

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderStatus;
use App\Models\Setting;
use Extensions\CourierFulfillment\Console\Commands\PollCourierStatus;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcClient;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcFulfillment;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcTrackingStatus;
use Extensions\CourierFulfillment\Models\TrackingNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What happens to an LBC booking after it is made: the waybill printed from
 * what was sent, tracking read from Track and Trace (many events share
 * StatusId 0, so the text decides), the scheduled poll, and the cancel that
 * can only ever be local.
 */
class LbcShipmentLifecycleTest extends TestCase
{
    use LbcBookingFixtures;
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n% demo waybill\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLbcBooking();
        $this->signIn();
        Storage::fake('local');

        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->registerCommand($this->app->make(PollCourierStatus::class));
    }

    /** A booked order, through the real booking path, with tracking and waybill answers faked too. */
    private function booked(array $tracking = [], array $orderOverrides = []): Order
    {
        $this->fakeLbc(null, [
            '*' . LbcClient::WAYBILL_PATH => Http::response(['Rendered_Byte' => base64_encode(self::PDF)]),
            '*' . LbcClient::TRACK_PATH => fn () => Http::response(self::trackAnswer($tracking)),
        ]);

        $order = $this->bookable($orderOverrides);
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())->assertSessionHas('success');

        return $this->freshOrder($order);
    }

    /** A Track and Trace SOAP answer; no events means "No Record Found". */
    private static function trackAnswer(array $events): string
    {
        if ($events === []) {
            return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
                . '<LBCTrackAndTraceResponse xmlns="http://tempuri.org/"><LBCTrackAndTraceResult><TrackingStatus>'
                . '<StatusCode>201</StatusCode><StatusMessage>NO RECORD FOUND</StatusMessage>'
                . '</TrackingStatus></LBCTrackAndTraceResult></LBCTrackAndTraceResponse></soap:Body></soap:Envelope>';
        }

        $history = '';
        foreach ($events as [$id, $date, $time, $text]) {
            $history .= "<TrackingHistory><StatusId>{$id}</StatusId><DatePosted>{$date}</DatePosted>"
                . '<StatusandLocation>' . htmlspecialchars($text, ENT_XML1) . "</StatusandLocation><DatePostedTime>{$time}</DatePostedTime>"
                . '<Remarks /></TrackingHistory>';
        }

        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<LBCTrackAndTraceResponse xmlns="http://tempuri.org/"><LBCTrackAndTraceResult><TrackingStatus>'
            . '<StatusCode>0001</StatusCode><StatusMessage>SUCCESS!</StatusMessage>'
            . '<TrackingDetails><TrackingDetails><TrackingNo>' . self::SERIES_FIRST . '</TrackingNo></TrackingDetails></TrackingDetails>'
            . '<TrackingHistory>' . $history . '</TrackingHistory>'
            . '</TrackingStatus></LBCTrackAndTraceResult></LBCTrackAndTraceResponse></soap:Body></soap:Envelope>';
    }

    // --- Waybill ------------------------------------------------------------------

    public function test_the_waybill_is_made_from_the_request_that_was_sent_and_kept(): void
    {
        $order = $this->booked([], ['courier_comment' => 'Call before delivery']);

        // The order changes after booking; the label must not.
        $order->forceFill(['shipping_address_1' => '99 Changed Street'])->save();

        $this->get($this->queueUrl('/' . $order->id . '/label'))
            ->assertRedirect($this->queueUrl('/' . $order->id . '/label-file'));

        $label = Http::recorded(fn (Request $r) => str_contains($r->url(), LbcClient::WAYBILL_PATH))->values()[0][0]->data();
        $this->assertSame((string) self::SERIES_FIRST, $label['TrackingNo']);
        $this->assertStringStartsWith('2 Sample Avenue', $label['ConsigneeAddress']);
        $this->assertSame('Call before delivery', $label['SpecialInstructions']);
        $this->assertSame('BOX', $label['ProductName']);
        $this->assertSame('20x15x10', $label['LWH']);
        $this->assertSame('None', $label['DeliveryRequirement']);

        $this->get($this->queueUrl('/' . $order->id . '/label-file'))->assertOk()->assertDownload('label-order-' . $order->id . '.pdf');

        // Printed again: served from the stored file, LBC is not asked twice.
        $this->get($this->queueUrl('/' . $order->id . '/label'));
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), LbcClient::WAYBILL_PATH)));
    }

    public function test_a_cod_waybill_names_the_amount_to_collect(): void
    {
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'cod_payment_methods'], ['value' => json_encode(['cod'])]);
        $order = $this->booked([], ['payment_method' => 'cod', 'total' => 1350, 'subtotal' => 1200, 'shipping_cost' => 150]);

        $this->get($this->queueUrl('/' . $order->id . '/label'));

        $label = Http::recorded(fn (Request $r) => str_contains($r->url(), LbcClient::WAYBILL_PATH))->values()[0][0]->data();
        $this->assertSame('COD', $label['DeliveryRequirement']);
        $this->assertSame('1350', $label['Amount']);
        $this->assertSame('1350', $label['DeclaredValue']);
    }

    public function test_a_waybill_without_a_stored_request_is_refused_without_asking_lbc(): void
    {
        $order = $this->booked();
        $order->forceFill(['courier_meta' => json_encode(['mode' => 'test'])])->save();

        $this->get($this->queueUrl('/' . $order->id . '/label'))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'no stored LBC request'));
        $this->assertCount(0, Http::recorded(fn (Request $r) => str_contains($r->url(), LbcClient::WAYBILL_PATH)));
    }

    // --- Tracking -----------------------------------------------------------------

    public function test_no_record_found_leaves_the_booking_as_it_is(): void
    {
        $order = $this->booked();

        $this->post($this->queueUrl('/' . $order->id . '/track'))->assertSessionHas('success', 'Status: unchanged');
        $this->assertSame(LbcTrackingStatus::BOOKED, $this->freshOrder($order)->courier_status);
    }

    public function test_events_sharing_status_id_zero_are_told_apart_by_their_text(): void
    {
        $order = $this->booked([
            [0, '11/05/2026', '02:10:00 PM', 'PICKED UP BY LBC'],
            [0, '11/05/2026', '06:00:00 PM', 'Shipment has been received at LBC CARGO FACILITY.'],
            [0, '11/06/2026', '08:00:00 AM', 'Shipment is en route to MAKATI DELIVERY TEAM. Next update for this shipment will come within 1 day/s.'],
            [8002, '11/06/2026', '09:30:00 AM', 'Please expect delivery within the day.'],
        ]);

        $this->post($this->queueUrl('/' . $order->id . '/track'))->assertSessionHas('success', 'Status: out_for_delivery');

        $fresh = $this->freshOrder($order);
        $this->assertSame(LbcTrackingStatus::OUT_FOR_DELIVERY, $fresh->courier_status);
        $timeline = $this->meta($order)['timeline'];
        $this->assertSame(['picked_up', 'received', 'forwarded', 'out_for_delivery'], array_column($timeline, 'status'));
        $this->assertSame('Shipment has been received at LBC CARGO FACILITY.', $timeline[1]['detail']);
        $this->assertSame(\Carbon\CarbonImmutable::parse('2026-11-05 14:10:00', 'Asia/Manila')->getTimestamp(), $timeline[0]['at']);
    }

    public function test_status_text_is_read_safely(): void
    {
        $this->assertSame(LbcTrackingStatus::COD_REMITTED, LbcTrackingStatus::forEvent(0, 'Deposited to Account of Shipper'));
        $this->assertSame(LbcTrackingStatus::READY_FOR_PICKUP, LbcTrackingStatus::forEvent('0', 'Shipment is now ready for pick up at LBC MAKATI'));
        $this->assertSame(LbcTrackingStatus::DELIVERED, LbcTrackingStatus::forEvent(1, 'Received by Juan Dela Cruz on 11/06/2026'));
        $this->assertSame(LbcTrackingStatus::RETURNED, LbcTrackingStatus::forEvent(51, 'RTS'));
        $this->assertSame(LbcTrackingStatus::FAILED_ATTEMPT, LbcTrackingStatus::forEvent(6, 'CONSIGNEE OUT'));
        $this->assertSame(LbcTrackingStatus::DELAYED, LbcTrackingStatus::forEvent(19, 'LATE ARRIVAL'));
        // An id we do not know is read by its text, and never as a delivery
        // when the text says it was not delivered.
        $this->assertSame(LbcTrackingStatus::DELAYED, LbcTrackingStatus::forEvent(777, 'Your shipment was not delivered due to an unforeseen event.'));
        $this->assertSame(LbcTrackingStatus::IN_TRANSIT, LbcTrackingStatus::forEvent(777, 'Something new'));

        $this->assertSame('delivered', LbcTrackingStatus::phase('cod_remitted')['phase']);
        $this->assertSame('failed', LbcTrackingStatus::phase('needs_attention')['phase']);
        $this->assertSame('returning', LbcTrackingStatus::phase('returned')['phase']);
        $this->assertSame('to_handover', LbcTrackingStatus::phase('unconfirmed')['phase']);
        $this->assertSame('in_transit', LbcTrackingStatus::phase('delayed')['phase']);
    }

    public function test_a_mapped_status_moves_the_order_and_names_lbc(): void
    {
        $shipped = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Shipped'], ['sort_order' => 5]);
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'lbc_status_map'], ['value' => json_encode([
            'picked_up' => ['order_status_id' => $shipped->id],
        ])]);
        $order = $this->booked([[0, '11/05/2026', '02:10:00 PM', 'PICKED UP BY LBC']]);

        $this->post($this->queueUrl('/' . $order->id . '/track'));

        $this->assertSame($shipped->id, (int) $this->freshOrder($order)->order_status_id);
        $this->assertTrue(OrderHistory::where('order_id', $order->id)->where('comment', 'LBC: Picked up by LBC')->exists());
    }

    // --- The scheduled poll -------------------------------------------------------

    public function test_the_poll_asks_active_bookings_at_its_own_interval_and_stops_when_final(): void
    {
        $answer = [[0, '11/05/2026', '02:10:00 PM', 'PICKED UP BY LBC']];
        $this->fakeLbc(null, [
            '*' . LbcClient::TRACK_PATH => function () use (&$answer) {
                return Http::response(self::trackAnswer($answer));
            },
        ]);
        $order = $this->bookable();
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())->assertSessionHas('success');
        $tracks = fn () => Http::recorded(fn (Request $r) => str_contains($r->url(), LbcClient::TRACK_PATH))->count();

        $this->artisan('courier:poll-status')->assertSuccessful();
        $this->assertSame(1, $tracks());
        $this->assertSame(LbcTrackingStatus::PICKED_UP, $this->freshOrder($order)->courier_status);

        // Ten minutes later the poll runs again but LBC is not asked yet.
        $this->travel(10)->minutes();
        $this->artisan('courier:poll-status')->assertSuccessful();
        $this->assertSame(1, $tracks());

        // Half an hour on (in transit), it is.
        $this->travel(25)->minutes();
        $answer[] = [1, '11/06/2026', '11:00:00 AM', 'Received by Juan Dela Cruz on 11/06/2026'];
        $this->artisan('courier:poll-status')->assertSuccessful();
        $this->assertSame(2, $tracks());
        $this->assertSame(LbcTrackingStatus::DELIVERED, $this->freshOrder($order)->courier_status);

        // Delivered is final: never asked again.
        $this->travel(2)->hours();
        $this->artisan('courier:poll-status')->assertSuccessful();
        $this->assertSame(2, $tracks());
    }

    public function test_before_pickup_the_poll_waits_an_hour(): void
    {
        $order = $this->booked();
        $tracks = fn () => Http::recorded(fn (Request $r) => str_contains($r->url(), LbcClient::TRACK_PATH))->count();

        $this->artisan('courier:poll-status');
        $this->assertSame(1, $tracks());
        $this->travel(45)->minutes();
        $this->artisan('courier:poll-status');
        $this->assertSame(1, $tracks());
        $this->travel(20)->minutes();
        $this->artisan('courier:poll-status');
        $this->assertSame(2, $tracks());
        $this->assertSame(LbcTrackingStatus::BOOKED, $this->freshOrder($order)->courier_status);
    }

    // --- Cancel ---------------------------------------------------------------------

    public function test_cancel_is_local_keeps_the_number_spent_and_says_to_call_lbc(): void
    {
        $order = $this->booked();
        $before = Http::recorded()->count();

        $this->post($this->queueUrl('/' . $order->id . '/cancel'))
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Cancelled booking ' . self::SERIES_FIRST)
                && str_contains($m, 'LBC has no cancel API. Call LBC to void this booking.'));

        $this->assertSame($before, Http::recorded()->count(), 'Nothing is sent to LBC on cancel.');

        $fresh = $this->freshOrder($order);
        $this->assertNull($fresh->courier_tracking_number);
        $this->assertSame($this->toPack->id, (int) $fresh->order_status_id);
        $this->assertSame((string) self::SERIES_FIRST, $this->meta($order)['booking_history'][0]['tracking_number']);
        $this->assertSame(TrackingNumber::SPENT, TrackingNumber::where('number', self::SERIES_FIRST)->value('status'));
        $this->assertTrue(OrderHistory::where('order_id', $order->id)->where('comment', 'like', '%Call LBC to void this booking.%')->exists());

        // Booking again takes the next number, never the cancelled one.
        $this->post($this->queueUrl('/' . $order->id . '/book'), $this->bookingForm())->assertSessionHas('success');
        $this->assertSame((string) (self::SERIES_FIRST + 1), $this->freshOrder($order)->courier_tracking_number);
    }

    public function test_the_queue_warns_that_cancelling_does_not_reach_lbc(): void
    {
        $this->booked();

        $this->get($this->queueUrl('?filter=to_ship&sub=to_handover'))
            ->assertOk()
            ->assertSee('This cancels the booking in this store only. LBC has no cancel API. Call LBC to void this booking.')
            ->assertSee('Print waybill');
    }

    public function test_a_parcel_already_picked_up_cannot_be_cancelled(): void
    {
        $order = $this->booked();
        $order->forceFill(['courier_status' => LbcTrackingStatus::PICKED_UP])->save();

        $this->assertNotContains(LbcTrackingStatus::PICKED_UP, (new LbcFulfillment())->cancelableStatuses());
        $this->get($this->queueUrl('?filter=to_ship&sub=to_handover'))->assertDontSee('This cancels the booking in this store only.');
    }
}
