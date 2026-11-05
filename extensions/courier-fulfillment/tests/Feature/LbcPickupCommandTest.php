<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/LbcFixtures.php';

use Extensions\CourierFulfillment\Couriers\Lbc\LbcClient;
use Extensions\CourierFulfillment\Models\TrackingNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * courier:lbc-poc end to end against faked LBC answers: a dry run sends no
 * pickup and takes no number; a confirmed run sends once and settles the
 * number by the answer.
 */
class LbcPickupCommandTest extends TestCase
{
    use LbcFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLbc();
        $this->pickupAddress();

        // $this->artisan() reuses the console application built before the
        // extension booted, so its commands are added to it directly.
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->registerCommand($this->app->make(\Extensions\CourierFulfillment\Console\Commands\LbcPickupProofOfConcept::class));
    }

    private static function isPickup(Request $request): bool
    {
        return str_contains($request->url(), LbcClient::PICKUP_PATH);
    }

    public function test_the_dry_run_never_sends_and_never_consumes(): void
    {
        $this->fakeLookups();
        $order = $this->order();
        $output = tempnam(sys_get_temp_dir(), 'lbc');

        $this->artisan('courier:lbc-poc', ['order' => $order->id, '--mode' => 'DROP OFF', '--output' => $output])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Tracking number it would use: ' . self::SERIES_FIRST)
            ->expectsOutputToContain('<ReferenceNoTwo>DROP OFF</ReferenceNoTwo>')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $request) => self::isPickup($request));
        $this->assertSame(0, DB::table('courier_fulfillment_tracking_number_series')->count());
        $this->assertSame(0, TrackingNumber::count());

        $xml = (string) file_get_contents($output);
        unlink($output);
        $this->assertStringNotContainsString(self::ACCOUNT_NO, $xml);
        $this->assertStringNotContainsString('09170000000', $xml);
        $this->assertStringContainsString('<TrackingNo>' . self::SERIES_FIRST . '</TrackingNo>', $xml);
    }

    public function test_an_accepted_request_spends_the_number(): void
    {
        $this->fakeLookups(pickup: ['*/' . LbcClient::PICKUP_PATH => Http::response(self::pickupAccepted((string) self::SERIES_FIRST))]);
        $order = $this->order();

        $this->artisan('courier:lbc-poc', ['order' => $order->id, '--confirm' => true])
            ->expectsOutputToContain('Code: 100')
            ->expectsOutputToContain('is spent')
            ->assertSuccessful();

        Http::assertSentCount(7);
        Http::assertSent(function (Request $request) {
            if (!self::isPickup($request)) {
                return false;
            }
            $xml = simplexml_load_string($request->body());

            return $request->hasHeader('Ocp-Apim-Subscription-Key', 'pickup-key')
                && str_starts_with($request->header('Content-Type')[0] ?? '', 'text/xml')
                && (string) $xml->TrackingNo === (string) self::SERIES_FIRST
                && (string) $xml->ShipperAccountNo === self::ACCOUNT_NO;
        });

        $number = TrackingNumber::sole();
        $this->assertSame(self::SERIES_FIRST, $number->number);
        $this->assertSame(TrackingNumber::SPENT, $number->status);
        $this->assertSame($order->id, $number->order_id);
    }

    public function test_a_rejected_request_releases_the_number_for_the_next_attempt(): void
    {
        $answers = [
            Http::response(self::pickupRejected('107', 'Shipper Account No. is not valid')),
            Http::response(self::pickupAccepted((string) self::SERIES_FIRST)),
        ];
        $this->fakeLookups(pickup: ['*/' . LbcClient::PICKUP_PATH => Http::sequence($answers)]);
        $order = $this->order();

        $this->artisan('courier:lbc-poc', ['order' => $order->id, '--confirm' => true])
            ->expectsOutputToContain('Shipper account number is missing or not valid (code 107)')
            ->expectsOutputToContain('was released')
            ->assertFailed();

        $this->assertSame(TrackingNumber::RELEASED, TrackingNumber::where('number', self::SERIES_FIRST)->value('status'));

        // The next attempt reuses the released number.
        $this->artisan('courier:lbc-poc', ['order' => $order->id, '--confirm' => true])->assertSuccessful();

        $sent = [];
        Http::recorded(function (Request $request) use (&$sent) {
            if (self::isPickup($request)) {
                $sent[] = (string) simplexml_load_string($request->body())->TrackingNo;
            }
        });
        $this->assertSame([(string) self::SERIES_FIRST, (string) self::SERIES_FIRST], $sent);
        $this->assertSame(TrackingNumber::SPENT, TrackingNumber::sole()->status);
    }

    public function test_an_unknown_outcome_spends_the_number_and_never_retries(): void
    {
        $pickups = 0;
        $this->fakeLookups(pickup: ['*/' . LbcClient::PICKUP_PATH => function () use (&$pickups) {
            $pickups++;
            throw new ConnectionException('timed out');
        }]);
        $order = $this->order();

        $this->artisan('courier:lbc-poc', ['order' => $order->id, '--confirm' => true])
            ->expectsOutputToContain('The outcome is unknown')
            ->assertFailed();

        $this->assertSame(1, $pickups);

        $this->assertSame(TrackingNumber::SPENT, TrackingNumber::sole()->status);
        $this->assertSame(self::SERIES_FIRST + 1, app(\Extensions\CourierFulfillment\TrackingNumbers\TrackingNumberSeriesService::class)
            ->peek('lbc', self::SERIES_FIRST, self::SERIES_LAST)->number);
    }

    public function test_an_unserviceable_address_refuses_before_any_number_or_send(): void
    {
        $this->fakeLookups(deliveryStatus: 'Not Serviceable', pickup: ['*/' . LbcClient::PICKUP_PATH => Http::response(self::pickupAccepted('0'))]);
        $order = $this->order();

        $this->artisan('courier:lbc-poc', ['order' => $order->id, '--confirm' => true])
            ->expectsOutputToContain('LBC does not serve the delivery address')
            ->assertFailed();

        Http::assertNotSent(fn (Request $request) => self::isPickup($request));
        $this->assertSame(0, TrackingNumber::count());
    }

    public function test_the_area_code_falls_back_to_the_region_when_no_category_is_given(): void
    {
        $this->fakeLookups(category: null);
        $order = $this->order();

        $this->artisan('courier:lbc-poc', ['order' => $order->id])
            ->expectsOutputToContain('area MMA (from region)')
            ->expectsOutputToContain('<DestinationCode>MMA</DestinationCode>')
            ->assertSuccessful();
    }

    public function test_cash_on_delivery_orders_are_refused(): void
    {
        $this->fakeLookups();
        $order = $this->order(['payment_method' => 'cod', 'payment_method_title' => 'Cash on Delivery']);

        $this->artisan('courier:lbc-poc', ['order' => $order->id, '--confirm' => true])
            ->expectsOutputToContain('Cash on delivery')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_a_used_up_series_refuses_without_sending(): void
    {
        $this->fakeLookups(pickup: ['*/' . LbcClient::PICKUP_PATH => Http::response(self::pickupAccepted('0'))]);
        $order = $this->order();
        DB::table('courier_fulfillment_tracking_number_series')->insert([
            'courier' => 'lbc', 'first_number' => self::SERIES_FIRST, 'last_number' => self::SERIES_LAST,
            'last_issued_number' => self::SERIES_LAST, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('courier:lbc-poc', ['order' => $order->id, '--confirm' => true])
            ->expectsOutputToContain('used up')
            ->assertFailed();

        Http::assertNotSent(fn (Request $request) => self::isPickup($request));
    }
}
