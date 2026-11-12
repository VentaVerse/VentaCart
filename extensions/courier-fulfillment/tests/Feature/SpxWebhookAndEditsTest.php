<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/SpxBookingFixtures.php';

use App\Models\Order;
use Extensions\CourierFulfillment\Couriers\Spx\SpxBookingEdits;
use Extensions\CourierFulfillment\Couriers\Spx\SpxClient;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SPX's pushes (4.1 tracking, 4.2 evidence) and editing a booking (1.7).
 * Every push is answered 200, whatever happens to it.
 */
class SpxWebhookAndEditsTest extends TestCase
{
    use RefreshDatabase;
    use SpxBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpx();
    }

    private function bookedOrder(array $meta = [], string $status = '1001'): Order
    {
        $order = $this->spxOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPXPH000000000001',
            'courier_status' => $status,
            'courier_meta' => json_encode(array_merge([
                'reference_id' => '20261113DEMO01', 'batch_no' => 207982800897461,
                'method' => 'dropoff', 'shipping_payment' => 'sender_pay', 'parcel' => 'medium',
                'weight_kg' => 1.2, 'cod' => false, 'insured_value' => 0, 'estimated_shipping_fee' => 85,
            ], $meta)),
        ])->save();

        return $order;
    }

    /** @return array<string, string> */
    private function signed(string $raw, ?int $timestamp = null, bool $withAppId = true): array
    {
        $timestamp ??= now()->getTimestamp();
        $headers = [
            'check-sign' => SpxClient::sign(424242, $timestamp, 5150, $raw, 'fake-app-secret-not-real'),
            'timestamp' => (string) $timestamp,
            'random-num' => '5150',
            'Content-Type' => 'application/json',
        ];
        if ($withAppId) {
            $headers['app-id'] = '424242';
        }

        return $headers;
    }

    private function push(array $body, ?array $headers = null): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($body);
        $headers ??= $this->signed($raw);

        return $this->call('POST', '/webhooks/courier/spx', [], [], [], $this->server($headers), $raw);
    }

    private function server(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[$key === 'CONTENT_TYPE' ? $key : 'HTTP_' . $key] = $value;
        }

        return $server;
    }

    // --- webhooks ----------------------------------------------------------------

    public function test_a_signed_push_updates_status_facts_and_cost(): void
    {
        $order = $this->bookedOrder([], '2006');

        $this->push([
            'status_code' => '4001', 'tracking_no' => 'SPXPH000000000001', 'order_id' => '20261113DEMO01',
            'timestamp' => now()->getTimestamp(), 'actual_shipping_fee' => 92.5,
            'edd_min' => 1, 'edd_max' => 3, 'driver_name' => 'Demo Rider', 'driver_phone_number' => '09170000009',
            'proof_of_delivery_list' => ['https://example.com/pod-1.jpg', 'javascript:alert(1)'],
        ])->assertOk()->assertJson(['ok' => true]);

        $order->refresh();
        $meta = $this->spxMeta($order);
        $this->assertSame('4001', $order->courier_status);
        $this->assertEquals(92.5, $meta['webhook_actual_fee']);
        $this->assertEquals(92.5, $order->shipping_cost_actual);
        $this->assertSame(['min' => 1, 'max' => 3, 'source' => 'webhook'], $meta['edd']);
        $this->assertSame(['name' => 'Demo Rider', 'phone' => '09170000009'], $meta['driver']);
        $this->assertSame(['epod' => ['https://example.com/pod-1.jpg']], $meta['proofs']);
    }

    public function test_a_push_without_an_app_id_header_is_checked_with_the_stores_own(): void
    {
        $order = $this->bookedOrder();
        $body = ['status_code' => '2001', 'tracking_no' => 'SPXPH000000000001'];
        $raw = json_encode($body);

        $this->call('POST', '/webhooks/courier/spx', [], [], [], $this->server($this->signed($raw, withAppId: false)), $raw)->assertOk();

        $this->assertSame('2001', $order->fresh()->courier_status);
    }

    public function test_a_push_is_matched_by_its_spx_order_id_when_it_carries_no_tracking_number(): void
    {
        $order = $this->bookedOrder();

        $this->push(['status_code' => '2001', 'order_id' => '20261113DEMO01'])->assertOk();

        $this->assertSame('2001', $order->fresh()->courier_status);
    }

    public function test_a_bad_signature_or_stale_timestamp_is_answered_200_logged_and_dropped(): void
    {
        $order = $this->bookedOrder();
        Log::spy();
        $body = ['status_code' => '4001', 'tracking_no' => 'SPXPH000000000001', 'driver_name' => 'Demo Rider', 'driver_phone_number' => '09170000009'];
        $raw = json_encode($body);

        $tampered = $this->signed($raw);
        $tampered['check-sign'] = str_repeat('0', 64);
        $this->push($body, $tampered)->assertOk()->assertJson(['ok' => true]);

        $stale = $this->signed($raw, now()->subSeconds(601)->getTimestamp());
        $this->push($body, $stale)->assertOk();

        $this->assertSame('1001', $order->fresh()->courier_status);
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            $logged = json_encode($context);

            return $message === 'SPX webhook rejected'
                && $context['fields']['tracking_no'] === 'SPXPH000000000001'
                && !str_contains($logged, 'Demo Rider')
                && !str_contains($logged, '09170000009');
        })->twice();
    }

    public function test_an_evidence_push_adds_proofs_without_touching_the_status(): void
    {
        $order = $this->bookedOrder([], '4001');

        $this->push([
            'tracking_no' => 'SPXPH000000000001', 'customer_order_id' => '20261113DEMO01',
            'epod_list' => ['https://example.com/pod.jpg'], 'epop_list' => ['https://example.com/pop.jpg'],
        ])->assertOk();

        $order->refresh();
        $this->assertSame('4001', $order->courier_status);
        $this->assertSame(['epod' => ['https://example.com/pod.jpg'], 'epop' => ['https://example.com/pop.jpg']], $this->spxMeta($order)['proofs']);
    }

    // --- booking edits -----------------------------------------------------------

    public function test_an_edit_resends_the_order_under_its_tracking_number_and_clears_the_label(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(SpxFulfillment::labelStoragePath('SPXPH000000000001'), '%PDF old');
        $order = $this->bookedOrder(['cod' => true, 'cod_amount' => 1100]);
        // The order total changed after booking; the COD booked stays.
        $order->forceFill(['total' => 1500])->save();

        $refusal = (new SpxBookingEdits($this->spx()))->update($order, ['weight_kg' => 2.5, 'parcel' => 'large']);

        $this->assertNull($refusal);
        $sent = $this->spxSent('batch_update_order')['orders'][0];
        $this->assertSame('SPXPH000000000001', $sent['tracking_no']);
        $this->assertSame('20261113DEMO01', $sent['order_id']);
        $this->assertEquals(2.5, $sent['parcel_info']['parcel_weight']);
        $this->assertSame(1100, $sent['fulfillment_info']['cod_amount']);
        $this->assertSame(2, $sent['fulfillment_info']['collect_type']);

        $meta = $this->spxMeta($order);
        $this->assertCount(1, $meta['booking_updates']);
        $this->assertEquals(2.5, $meta['weight_kg']);
        $this->assertSame('SPXPH000000000001', $order->fresh()->courier_tracking_number);
        Storage::disk('local')->assertMissing(SpxFulfillment::labelStoragePath('SPXPH000000000001'));
    }

    public function test_edits_stop_once_spx_has_the_parcel_or_the_limit_is_reached(): void
    {
        config(['courier-fulfillment.spx.booking_edits_max' => 1]);

        $collected = $this->bookedOrder([], '2001');
        $this->assertStringContainsString('pending pickup', (new SpxBookingEdits($this->spx()))->update($collected, ['weight_kg' => 2])->detail);

        $edited = $this->bookedOrder(['booking_updates' => [['at' => now()->toIso8601String()]]]);
        $this->assertFalse(SpxBookingEdits::allowed($edited));
        $this->assertStringContainsString('at most 1 changes', (new SpxBookingEdits($this->spx()))->update($edited, ['weight_kg' => 2])->detail);

        $this->assertSame(0, $this->spxSentCount('batch_update_order'));
    }

    public function test_a_refused_edit_changes_nothing(): void
    {
        $this->spxFake(['batch_update_order' => ['orders' => [], 'fail_list' => [['ret_code' => 10001, 'message' => 'weight too heavy']]]]);
        $order = $this->bookedOrder();

        $refusal = (new SpxBookingEdits($this->spx()))->update($order, ['weight_kg' => 4.5, 'parcel' => 'large']);

        $this->assertSame('Too heavy for SPX. Weight is 4.5 kg.', $refusal->message());
        $meta = $this->spxMeta($order);
        $this->assertArrayNotHasKey('booking_updates', $meta);
        $this->assertEquals(1.2, $meta['weight_kg']);
    }
}
