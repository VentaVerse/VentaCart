<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/SpxBookingFixtures.php';

use App\Models\Order;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Booking with SPX: what SPX is sent (addresses on its own levels, the real
 * weight, COD and insurance), what is refused before anything is created,
 * and what happens when SPX's answer is unknown. Every SPX answer is faked.
 */
class SpxBookingTest extends TestCase
{
    use RefreshDatabase;
    use SpxBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpx();
    }

    private function book(Order $order, array $opts = []): array
    {
        return $this->spx()->createShipment($order, array_merge([
            'service' => 'standard', 'parcel' => 'medium', 'shipping_payment' => 'sender_pay', 'weight_kg' => 1.2,
        ], $opts));
    }

    // --- addresses ---------------------------------------------------------------

    public function test_addresses_go_on_spx_levels_with_their_own_reference(): void
    {
        $result = $this->book($this->spxOrder());

        $this->assertTrue($result['success']);
        $this->assertSame('SPXPH000000000001', $result['tracking_number']);

        $sent = $this->createdOrder();
        $this->assertSame(['Metro Manila', 'Metro Manila', 'Makati City', 'Bel-air'], [
            $sent['sender_info']['sender_state'], $sent['sender_info']['sender_city'],
            $sent['sender_info']['sender_district'], $sent['sender_info']['sender_street'],
        ]);
        $this->assertSame(['Visayas', 'Cebu', 'Cebu City', 'Apas'], [
            $sent['deliver_info']['deliver_state'], $sent['deliver_info']['deliver_city'],
            $sent['deliver_info']['deliver_district'], $sent['deliver_info']['deliver_street'],
        ]);
        $this->assertSame('Juan Dela Cruz', $sent['deliver_info']['deliver_name']);

        // Its own order_id, kept on the booking, and the same one the fee check saw.
        $this->assertMatchesRegularExpression('/^\d{8}[A-Z0-9]{6}$/', $sent['order_id']);
        $this->assertSame($sent['order_id'], $result['meta']['reference_id']);
        $this->assertSame($sent['order_id'], $this->spxSent('batch_check_order')['orders'][0]['order_id']);
        // The tracking link comes from the create result.
        $this->assertSame(['delivery' => 'https://uat.spx.ph/track?SPXPH000000000001'], $result['meta']['tracking_links']);
    }

    public function test_two_bookings_of_one_order_never_share_an_order_id(): void
    {
        $order = $this->spxOrder();
        $first = $this->book($order)['meta']['reference_id'];
        $second = $this->book($order)['meta']['reference_id'];

        $this->assertNotSame($first, $second);
    }

    public function test_an_unlisted_barangay_is_refused_and_never_replaced_by_the_city(): void
    {
        $result = $this->book($this->spxOrder(['shipping_barangay' => 'Not A Barangay']));

        $this->assertFalse($result['success']);
        $this->assertStringContainsString("SPX doesn't list the customer's barangay", $result['error']);
        $this->assertSame(0, $this->spxSentCount('batch_create_order'));

        $noBarangay = $this->book($this->spxOrder(['shipping_barangay' => '']));
        $this->assertStringContainsString('has no barangay', $noBarangay['error']);
    }

    public function test_a_barangay_spx_does_not_deliver_to_is_refused_before_asking(): void
    {
        $result = $this->book($this->spxOrder(['shipping_barangay' => 'Alegria', 'shipping_city' => 'Caluya', 'shipping_state' => 'Antique', 'shipping_postcode' => '5711']));

        $this->assertFalse($result['success']);
        $this->assertSame("SPX doesn't deliver to this barangay.", $result['error']);
        Http::assertNothingSent();
    }

    public function test_a_pickup_from_a_barangay_spx_does_not_collect_from_is_refused_but_drop_off_books(): void
    {
        PickupAddress::query()->update(['barangay' => 'Pasil', 'city' => 'Cebu City', 'state' => 'Cebu', 'postcode' => '6000']);

        $pickup = $this->book($this->spxOrder(), ['method' => 'pickup']);
        $this->assertFalse($pickup['success']);
        $this->assertStringContainsString("SPX doesn't pick up", $pickup['error']);

        $this->assertTrue($this->book($this->spxOrder(), ['method' => 'dropoff'])['success']);
    }

    public function test_a_name_longer_than_spx_takes_is_refused(): void
    {
        $result = $this->book($this->spxOrder(['shipping_first_name' => str_repeat('A', 60), 'shipping_last_name' => 'Dela Cruz']));

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('longer than SPX takes (64 characters)', $result['error']);
    }

    // --- weight and size ---------------------------------------------------------

    public function test_the_real_weight_is_sent_never_the_presets_ceiling(): void
    {
        $this->book($this->spxOrder(), ['parcel' => 'large', 'weight_kg' => 2.35]);

        $parcel = $this->createdOrder()['parcel_info'];
        $this->assertEquals(2.35, $parcel['parcel_weight']);
        $this->assertEquals([45, 35, 20], [$parcel['parcel_length'], $parcel['parcel_width'], $parcel['parcel_height']]);
    }

    public function test_typed_sizes_beat_the_preset(): void
    {
        $this->book($this->spxOrder(), ['parcel' => 'small', 'length_cm' => 30, 'width_cm' => 22, 'height_cm' => 12]);

        $parcel = $this->createdOrder()['parcel_info'];
        $this->assertEquals([30, 22, 12], [$parcel['parcel_length'], $parcel['parcel_width'], $parcel['parcel_height']]);
    }

    public function test_a_parcel_heavier_than_its_pouch_or_with_no_weight_is_refused(): void
    {
        $heavy = $this->book($this->spxOrder(), ['parcel' => 'small', 'weight_kg' => 4]);
        $this->assertFalse($heavy['success']);
        $this->assertStringContainsString('more than the Small pouch takes (up to 3 kg)', $heavy['error']);

        $unweighed = $this->book($this->spxOrder(), ['weight_kg' => null]);
        $this->assertFalse($unweighed['success']);
        $this->assertStringContainsString('Enter the parcel weight', $unweighed['error']);

        $this->assertSame(0, $this->spxSentCount('batch_create_order'));
    }

    public function test_spxs_published_limits_are_checked_first(): void
    {
        $this->assertStringContainsString('under 50 kg', $this->book($this->spxOrder(), ['parcel' => 'custom', 'length_cm' => 40, 'width_cm' => 40, 'height_cm' => 40, 'weight_kg' => 50])['error']);
        $this->assertStringContainsString("can't exceed 150 cm", $this->book($this->spxOrder(), ['parcel' => 'custom', 'length_cm' => 151, 'width_cm' => 40, 'height_cm' => 40])['error']);
        Http::assertNothingSent();
    }

    public function test_spxs_fee_check_refusal_stops_the_booking_in_plain_words(): void
    {
        $this->spxFake(['batch_check_order' => [
            'ret_code' => 13051,
            'message' => 'check rule|7 |ruleName| MaxLengthExtremumRule failed, 35.000000 exceed max value 25.000000',
            'data' => null,
        ]]);

        $result = $this->book($this->spxOrder(), ['parcel' => 'medium']);

        $this->assertFalse($result['success']);
        $this->assertSame('Too long for SPX. Length is 35 cm. SPX takes up to 25 cm.', $result['error']);
        $this->assertStringContainsString('MaxLengthExtremumRule', $result['courier_error']);
        $this->assertSame(0, $this->spxSentCount('batch_create_order'));
    }

    public function test_a_refusal_keeps_spxs_own_words_on_the_order_for_support(): void
    {
        $this->spxFake(['batch_check_order' => ['ret_code' => 12054, 'message' => 'cod not supported for this account', 'data' => null]]);
        $order = $this->spxCodOrder(['total' => 1100]);

        $res = app(FulfillmentService::class)->book($order, ['courier' => 'spx', 'service' => 'standard', 'parcel' => 'medium', 'shipping_payment' => 'sender_pay', 'weight_kg' => 1]);

        $this->assertFalse($res['success']);
        $this->assertSame('COD not set up with SPX. Add a bank account with SPX to send COD parcels.', $res['error']);
        $refusal = $this->spxMeta($order)['last_booking_refusal'];
        $this->assertSame('SPX 12054: cod not supported for this account', $refusal['courier_said']);
    }

    public function test_an_unreachable_fee_check_does_not_block_the_booking(): void
    {
        $this->spxFake(['batch_check_order' => Http::response('gateway down', 502)]);

        $this->assertTrue($this->book($this->spxOrder())['success']);
    }

    // --- COD and insurance -------------------------------------------------------

    public function test_cod_collects_the_order_total_and_insurance_follows_the_tickbox(): void
    {
        $this->book($this->spxCodOrder(['total' => 1100]), ['insure' => true]);

        $sent = $this->createdOrder();
        $this->assertSame(1, $sent['fulfillment_info']['cod_collection']);
        $this->assertSame(1100, $sent['fulfillment_info']['cod_amount']);
        $this->assertEquals(1100, $sent['parcel_info']['express_insured_value']);
    }

    public function test_an_uninsured_prepaid_parcel_declares_zero(): void
    {
        $this->book($this->spxOrder());

        $sent = $this->createdOrder();
        $this->assertSame(0, $sent['fulfillment_info']['cod_collection']);
        $this->assertArrayNotHasKey('cod_amount', $sent['fulfillment_info']);
        $this->assertEquals(0, $sent['parcel_info']['express_insured_value']);
    }

    public function test_cod_that_is_not_whole_pesos_or_above_the_limit_is_refused_not_rounded(): void
    {
        $centavos = $this->book($this->spxCodOrder(['total' => 1100.50]));
        $this->assertFalse($centavos['success']);
        $this->assertStringContainsString('whole pesos only', $centavos['error']);

        $tooMuch = $this->book($this->spxCodOrder(['total' => 50001]));
        $this->assertStringContainsString('up to ₱50,000', $tooMuch['error']);

        Http::assertNothingSent();
    }

    public function test_the_erp_buyer_payment_decides_cod(): void
    {
        $this->book($this->spxOrder(['total' => 900]), ['buyer_payment' => 'cod']);

        $this->assertSame(900, $this->createdOrder()['fulfillment_info']['cod_amount']);
    }

    // --- unknown outcomes and 13103 ----------------------------------------------

    public function test_a_server_error_on_create_is_an_unknown_outcome_not_a_refusal(): void
    {
        $this->spxFake(['batch_create_order' => Http::response(['ret_code' => 99999, 'message' => 'internal error'], 500)]);
        $order = $this->spxOrder();

        $res = app(FulfillmentService::class)->book($order, ['courier' => 'spx', 'service' => 'standard', 'parcel' => 'medium', 'shipping_payment' => 'sender_pay', 'weight_kg' => 1.2]);

        $this->assertTrue($res['success']);
        $this->assertNull($res['tracking_number']);
        $this->assertSame('creating', $res['status']);
        $this->assertStringContainsString('did not confirm', $res['notice']);

        $meta = $this->spxMeta($order);
        $this->assertTrue($meta['outcome_unknown']);
        $this->assertArrayNotHasKey('batch_no', $meta);

        // The order cannot be booked again while SPX's answer is unknown.
        $again = app(FulfillmentService::class)->book($order->fresh(), ['courier' => 'spx', 'service' => 'standard', 'parcel' => 'medium', 'shipping_payment' => 'sender_pay', 'weight_kg' => 1.2]);
        $this->assertSame('creation_in_progress', $again['code']);
        $this->assertSame(1, $this->spxSentCount('batch_create_order'));
    }

    public function test_no_answer_at_all_is_an_unknown_outcome_too(): void
    {
        $this->spxFake(['batch_create_order' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $result = $this->book($this->spxOrder());

        $this->assertTrue($result['success']);
        $this->assertSame('creating', $result['status']);
        $this->assertTrue($result['meta']['outcome_unknown']);
    }

    public function test_a_retry_after_an_unknown_outcome_reuses_the_order_id_and_recovers_spxs_order(): void
    {
        $order = $this->spxOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creation_failed',
            'courier_meta' => json_encode(['reference_id' => '20261113RETRY1', 'outcome_unknown' => true]),
        ])->save();

        $this->spxFake([
            'get_order_create_result' => [
                'batch_no' => 1, 'task_status' => 4, 'fail_count' => 1, 'orders' => [],
                'fail_list' => [['order_id' => '20261113RETRY1', 'ret_code' => 13103, 'message' => 'order id has been used already']],
            ],
            'batch_search_order' => function ($request) {
                $asked = json_decode($request->body(), true)['tracking_no_list'][0];

                return $asked === '20261113RETRY1'
                    ? ['orders' => [['order_id' => '20261113RETRY1', 'tracking_no' => 'SPXPH000000000077', 'status_code' => '1001',
                        'deliver_info' => ['deliver_name' => 'Juan Dela Cruz', 'deliver_phone' => '639171234567']]], 'fail_list' => []]
                    : ['orders' => [], 'fail_list' => []];
            },
        ]);

        $result = $this->book($order->fresh());

        $this->assertSame('20261113RETRY1', $this->createdOrder()['order_id']);
        $this->assertTrue($result['success']);
        $this->assertSame('SPXPH000000000077', $result['tracking_number']);
        $this->assertSame(13103, $result['meta']['recovered']);
        $this->assertStringContainsString('already had order 20261113RETRY1', $result['notice']);
        // Track's names and addresses are not kept.
        $this->assertArrayNotHasKey('deliver_info', $result['meta']['booked_response']);
    }

    public function test_an_unknown_booking_is_resolved_by_the_poll_or_failed_after_its_grace_period(): void
    {
        $order = $this->spxOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_status' => 'creating',
            'courier_meta' => json_encode(['reference_id' => '20261113WAIT01', 'outcome_unknown' => true, 'pending' => true, 'unknown_since' => now()->toIso8601String()]),
        ])->save();
        $this->spxFake(['batch_search_order' => ['orders' => [], 'fail_list' => []]]);

        $this->assertNull($this->spx()->resolvePendingCreation($order));
        $this->assertSame('creating', $order->fresh()->courier_status);

        $this->travel(SpxFulfillment::UNKNOWN_OUTCOME_MINUTES + 1)->minutes();
        $this->assertNull($this->spx()->resolvePendingCreation($order->fresh()));
        $order->refresh();
        $this->assertSame('creation_failed', $order->courier_status);
        $this->assertStringContainsString('reuses 20261113WAIT01', $this->spxMeta($order)['creation_error']);

        // Found later by its order_id: the poll records the booking.
        $order->forceFill(['courier_status' => 'creating'])->save();
        $this->spxFake(['batch_search_order' => ['orders' => [['order_id' => '20261113WAIT01', 'tracking_no' => 'SPXPH000000000088', 'status_code' => '1001']], 'fail_list' => []]]);
        $this->assertSame('SPXPH000000000088', $this->spx()->resolvePendingCreation($order->fresh()));
        $this->assertArrayNotHasKey('outcome_unknown', $this->spxMeta($order));
    }

    public function test_a_still_running_batch_resolves_later_with_its_booked_data(): void
    {
        $this->spxFake(['get_order_create_result' => ['batch_no' => 207982800897461, 'task_status' => 3, 'orders' => [], 'fail_list' => []]]);
        $order = $this->spxOrder();

        $res = app(FulfillmentService::class)->book($order, ['courier' => 'spx', 'service' => 'standard', 'parcel' => 'medium', 'shipping_payment' => 'sender_pay', 'weight_kg' => 1.2]);
        $this->assertSame('creating', $res['status']);

        $this->spxFake();
        $this->assertSame('SPXPH000000000001', $this->spx()->resolvePendingCreation($order->fresh()));
        $meta = $this->spxMeta($order);
        $this->assertEquals(85, $meta['estimated_shipping_fee']);
        $this->assertSame(['delivery' => 'https://uat.spx.ph/track?SPXPH000000000001'], $meta['tracking_links']);
    }

    // --- pickup and drop-off -----------------------------------------------------

    public function test_a_caller_without_a_method_or_slot_still_books_drop_off(): void
    {
        $this->book($this->spxOrder());

        $this->assertSame(2, $this->createdOrder()['fulfillment_info']['collect_type']);
        $this->assertSame(0, $this->spxSentCount('get_pickup_time'));
    }

    public function test_a_pickup_without_a_window_books_the_earliest_offered(): void
    {
        $result = $this->book($this->spxOrder(), ['method' => 'pickup']);

        $info = $this->createdOrder()['fulfillment_info'];
        $this->assertSame(1, $info['collect_type']);
        $this->assertSame(1, $info['pickup_time_range_id']);
        $this->assertSame('earliest', $result['meta']['pickup_changes'][0]['reason']);
    }

    public function test_a_pickup_is_never_turned_into_a_drop_off_when_windows_cannot_be_fetched(): void
    {
        $this->spxFake(['get_pickup_time' => Http::response('down', 503)]);

        $result = $this->book($this->spxOrder(), ['method' => 'pickup']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString("Couldn't get SPX's pickup times", $result['error']);
        $this->assertSame(0, $this->spxSentCount('batch_create_order'));
    }

    public function test_a_window_past_spxs_cutoff_is_rebooked_once_on_the_next_window(): void
    {
        $tomorrow = now(config('app.timezone'))->addDay()->startOfDay()->getTimestamp();
        $refused = json_encode(['pickup_time' => $tomorrow, 'pickup_time_range_id' => 1, 'pickup_time_range' => '9:00am-12:00pm']);
        $creates = 0;
        $this->spxFake([
            'batch_create_order' => function () use (&$creates) {
                return ++$creates === 1
                    ? ['ret_code' => 10080, 'message' => 'pickup time is over cutoff', 'data' => null]
                    : ['batch_no' => 5];
            },
        ]);

        $result = $this->book($this->spxOrder(), ['method' => 'pickup', 'pickup_slot' => $refused]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $creates);
        $this->assertSame('cutoff', $result['meta']['pickup_changes'][0]['reason']);
        $this->assertStringContainsString('"pickup_time_range_id":2', $result['meta']['pickup_slot']);
    }

    public function test_pickup_windows_list_every_window_and_skip_past_days(): void
    {
        $yesterday = now(config('app.timezone'))->subDay()->startOfDay()->getTimestamp();
        $this->spxFake(['get_pickup_time' => [
            ['pickup_time' => $yesterday, 'slots' => [['pickup_time_range_id' => 1, 'pickup_time_range' => '9:00am-12:00pm']]],
            ['pickup_time' => now(config('app.timezone'))->addDay()->startOfDay()->getTimestamp(), 'slots' => [
                ['pickup_time_range_id' => 1, 'pickup_time_range' => '9:00am-12:00pm'],
                ['pickup_time_range_id' => 2, 'pickup_time_range' => '1:00pm-6:00pm'],
            ]],
        ]]);

        $slots = $this->spx()->getPickupSlots($this->spxOrder());

        $this->assertCount(2, $slots);
        $this->assertStringEndsWith('1:00pm-6:00pm', $slots[1]['label']);
    }

    // --- fees, status, labels ----------------------------------------------------

    public function test_the_estimate_reports_a_remote_area_fee_as_information(): void
    {
        $this->spxFake(['batch_check_order' => ['orders' => [['estimated_shipping_fee' => 120, 'remote_area_fee' => 35]], 'fail_list' => []]]);

        $fee = $this->spx()->estimateFee($this->spxOrder(), ['parcel' => 'medium', 'weight_kg' => 1]);

        $this->assertEquals(120, $fee->amount);
        $this->assertEquals(35, $fee->remote_area_fee);
        $this->assertTrue($fee->odz);
    }

    public function test_the_estimate_button_shows_spxs_refusal_in_plain_words(): void
    {
        $this->signIn();
        $this->spxFake(['batch_check_order' => ['orders' => [], 'fail_list' => [['ret_code' => 10017, 'message' => 'cod delivery not supported']]]]);
        $order = $this->spxCodOrder();

        $this->postJson($this->queueUrl('/' . $order->id . '/estimate'), ['courier' => 'spx', 'parcel' => 'medium', 'weight_kg' => '1'])
            ->assertOk()
            ->assertJson(['ok' => false, 'message' => "SPX doesn't collect COD here. Check the delivery address."]);
    }

    public function test_on_hold_is_in_transit_and_lost_or_damaged_are_final_failures(): void
    {
        $spx = $this->spx();

        $this->assertSame(['phase' => 'in_transit', 'step' => 2, 'failed' => false], $spx->statusPhase('3001'));
        $this->assertNotContains('3001', $spx->terminalStatuses());
        foreach (['5002', '5003'] as $status) {
            $this->assertSame('failed', $spx->statusPhase($status)['phase']);
            $this->assertContains($status, $spx->terminalStatuses());
        }
    }

    public function test_a_label_is_only_looked_up_for_a_plain_tracking_number(): void
    {
        $result = $this->spx()->printLabel('../../etc/passwd', 'pdf');

        $this->assertFalse($result['success']);
        $this->assertSame('That is not an SPX tracking number.', $result['error']);
    }

    public function test_the_booked_fee_is_the_store_cost_until_spx_reports_the_actual_one(): void
    {
        $spx = $this->spx();

        $this->assertSame(85.0, $spx->actualShippingCost(['estimated_shipping_fee' => 85]));
        $this->assertSame(90.0, $spx->actualShippingCost(['estimated_shipping_fee' => 85, 'webhook_actual_fee' => 90]));
        $this->assertSame(92.5, $spx->actualShippingCost(['estimated_shipping_fee' => 85, 'actual_fee' => ['actual_shipping_fee' => 92.5]]));
        $this->assertNull($spx->actualShippingCost([]));
    }

    public function test_a_booking_records_its_cost_and_tracking_link_on_the_order(): void
    {
        $order = $this->spxOrder();

        app(FulfillmentService::class)->book($order, ['courier' => 'spx', 'service' => 'standard', 'parcel' => 'medium', 'shipping_payment' => 'sender_pay', 'weight_kg' => 1.2]);

        $order->refresh();
        $this->assertEquals(85, $order->shipping_cost_actual);
        $this->assertSame('https://uat.spx.ph/track?SPXPH000000000001', $order->tracking_link);
    }
}
