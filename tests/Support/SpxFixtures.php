<?php

namespace Tests\Support;

use App\Models\Country;
use App\Models\Setting;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * SPX test fixtures: neutral credentials, a pickup address and a delivery
 * address that both exist on SPX's location list, and one fake for every
 * SPX endpoint so no test can reach SPX.
 *
 * spxFake() answers by endpoint; pass overrides keyed by the endpoint's
 * last path segment (batch_check_order, batch_create_order,
 * get_order_create_result, batch_search_order, batch_get_asf,
 * get_pickup_time, batch_cancel_order, batch_update_order) as either a
 * `data` array or a full response (callable or Http::response()).
 */
trait SpxFixtures
{
    /** @var array<string, mixed> */
    protected array $spxAnswers = [];

    protected bool $spxFaked = false;

    protected function spxCredentials(bool $enabled = true): void
    {
        foreach ([
            'spx_enabled' => $enabled ? '1' : '0',
            'spx_mode' => 'uat',
            'spx_app_id' => '424242',
            'spx_secret_key' => Crypt::encryptString('fake-app-secret-not-real'),
            'spx_user_id' => '999999',
            'spx_user_secret' => Crypt::encryptString('fake-user-secret-not-real'),
        ] as $key => $value) {
            Setting::updateOrCreate(['group' => 'courier', 'key' => $key], ['value' => $value]);
        }
    }

    /** Bel-air, Makati City, Metro Manila: listed by SPX for pickup and COD. */
    protected function spxPickupAddress(array $overrides = []): PickupAddress
    {
        $country = Country::firstOrCreate(
            ['iso2' => 'PH'],
            ['iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]
        );

        return PickupAddress::create(array_merge([
            'label'         => 'Demo warehouse',
            'contact_name'  => 'Demo Store',
            'contact_phone' => '09171112222',
            'address_1'     => '456 Sample Avenue',
            'barangay'      => 'Bel-Air',
            'district'      => null,
            'city'          => 'Makati City',
            'state'         => 'Metro Manila',
            'postcode'      => '1209',
            'country_id'    => $country->id,
            'is_default'    => true,
        ], $overrides));
    }

    /** Apas, Cebu City, Cebu: listed by SPX for delivery and COD. */
    protected function spxDeliveryAddress(): array
    {
        return [
            'shipping_first_name' => 'Juan',
            'shipping_last_name'  => 'Dela Cruz',
            'shipping_address_1'  => '123 Sample Street',
            'shipping_barangay'   => 'Apas',
            'shipping_city'       => 'Cebu City',
            'shipping_state'      => 'Cebu',
            'shipping_postcode'   => '6000',
            'shipping_phone'      => '09171234567',
        ];
    }

    protected static function spxOk(array $data): array
    {
        return ['ret_code' => 0, 'message' => 'success.', 'data' => $data];
    }

    /** A settled 1.5 answer with one created order. */
    protected static function spxCreated(string $tracking, array $extra = []): array
    {
        return [
            'batch_no' => 207982800897461, 'task_status' => 4, 'description' => 'Done',
            'total_count' => 1, 'success_count' => 1, 'fail_count' => 0, 'progress' => '100.00%',
            'orders' => [array_merge([
                'order_id' => '20261113DEMO01', 'tracking_no' => $tracking,
                'tracking_link' => "https://uat.spx.ph/track?{$tracking}",
                'estimated_shipping_fee' => 85,
            ], $extra)],
            'fail_list' => [],
        ];
    }

    /**
     * Fakes every SPX endpoint. Defaults: the fee check quotes 85, create
     * gives a batch, the batch settles with SPXPH000000000001, tracking says
     * 1001, two pickup windows tomorrow.
     *
     * @param array<string, mixed> $overrides
     */
    protected function spxFake(array $overrides = []): void
    {
        Http::preventStrayRequests();

        $tomorrow = now(config('app.timezone'))->addDay()->startOfDay()->getTimestamp();
        $defaults = [
            'batch_check_order' => ['orders' => [['estimated_shipping_fee' => 85]], 'fail_list' => []],
            'batch_create_order' => ['batch_no' => 207982800897461],
            'get_order_create_result' => self::spxCreated('SPXPH000000000001'),
            'batch_search_order' => ['orders' => [[
                'tracking_no' => 'SPXPH000000000001', 'status_code' => '1001',
                'tracking_link' => 'https://uat.spx.ph/track?SPXPH000000000001', 'routes' => [],
            ]], 'fail_list' => []],
            'batch_get_asf' => ['orders' => [], 'fail_list' => []],
            'get_pickup_time' => [[
                'pickup_time' => $tomorrow,
                'slots' => [
                    ['pickup_time_range_id' => 1, 'pickup_time_range' => '9:00am-12:00pm'],
                    ['pickup_time_range_id' => 2, 'pickup_time_range' => '1:00pm-6:00pm'],
                ],
            ]],
            'batch_cancel_order' => ['tracking_no_list' => ['SPXPH000000000001'], 'fail_list' => []],
            'batch_update_order' => ['orders' => [['tracking_no' => 'SPXPH000000000001']], 'fail_list' => []],
        ];

        // One fake per test, reading the latest answers: Http::fake() keeps
        // its first matching stub, so faking again would be ignored.
        $this->spxAnswers = array_merge($defaults, $overrides);
        if ($this->spxFaked) {
            return;
        }
        $this->spxFaked = true;

        Http::fake(function (Request $request) {
            $endpoint = basename((string) parse_url($request->url(), PHP_URL_PATH));
            if (!array_key_exists($endpoint, $this->spxAnswers)) {
                return Http::response(['ret_code' => 99999, 'message' => "unfaked SPX endpoint {$endpoint}"], 500);
            }
            $answer = $this->spxAnswers[$endpoint];
            if (is_callable($answer)) {
                $answer = $answer($request);
            }
            if ($answer instanceof \GuzzleHttp\Promise\PromiseInterface) {
                return $answer;
            }

            return Http::response(isset($answer['ret_code']) ? $answer : self::spxOk($answer));
        });
    }

    /** The body of the first request sent to an SPX endpoint. */
    protected function spxSent(string $endpoint): ?array
    {
        foreach (Http::recorded() as [$request]) {
            if (basename((string) parse_url($request->url(), PHP_URL_PATH)) === $endpoint) {
                return json_decode($request->body(), true);
            }
        }

        return null;
    }

    protected function spxSentCount(string $endpoint): int
    {
        $n = 0;
        foreach (Http::recorded() as [$request]) {
            if (basename((string) parse_url($request->url(), PHP_URL_PATH)) === $endpoint) {
                $n++;
            }
        }

        return $n;
    }
}
