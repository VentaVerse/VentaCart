<?php

namespace Tests\Feature\AdminV2;

use App\Models\Country;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Owner directive, 2026-08-19: the booking modal shows the money that
 * actually leaves the store for this shipment, next to "You pay shipping",
 * because on cash/gcash terms that figure is handed to the rider.
 *
 * Two facts about QuadX's rate endpoint make this file necessary, both
 * verified live against staging on 2026-08-19:
 *
 *  1. It answers the fee question TWICE. `shipping_fee` is the list price and
 *     `fees_adjustments.shipping_fee` is the same map after QuadX's own
 *     surcharges and campaigns (a box to Cebu quotes 490 list, 450 net).
 *     Billing follows the net map, so every figure Venta shows must too.
 *     Reading the first key whose name matched was how this shipped wrong.
 *  2. It prices insurance only when ASKED. Send `declared_value` and the
 *     response grows an `insurance_fee` key (1% of the value above the first
 *     free 500); omit it and there is no key at all. An absent key therefore
 *     means "uninsured", never "free", and the total must not invent one.
 *
 * Http::preventStrayRequests() throughout: a fake whose URL pattern missed
 * would otherwise fall through to a REAL booking API.
 */
class FulfillmentQuadXRiderTotalTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
        Http::preventStrayRequests();
    }

    // --- fixtures ------------------------------------------------------

    private const RATES = 'api.staging.quadx.xyz/v2/orders/estimates/rates';

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function enableQuadxStaging(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
    }

    private function configurePickupOrigin(): void
    {
        $country = Country::firstOrCreate(
            ['iso2' => 'PH'],
            ['iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]
        );

        PickupAddress::create([
            'label'         => 'Store pickup',
            'contact_name'  => 'Warehouse Team',
            'contact_phone' => '09171112222',
            'address_1'     => '456 Warehouse Ave',
            'barangay'      => 'San Bartolome',
            'city'          => 'Quezon City',
            'state'         => 'Metro Manila',
            'postcode'      => '1116',
            'country_id'    => $country->id,
        ]);
    }

    /** An order sitting where booking starts (To Pack). Total 1100 is the declared value when insured. */
    private function makeOrder(): Order
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );
        PlacementMap::put($status->id, 'to_pack');
        \Tests\Support\FulfillmentSettings::selectFor('to_pack', (int) $status->id);

        return Order::create([
            'store_id'            => 1,
            'order_status_id'     => $status->id,
            'first_name'          => 'Jane',
            'last_name'           => 'Doe',
            'email'               => 'jane@example.com',
            'shipping_first_name' => 'Jane',
            'shipping_last_name'  => 'Doe',
            'shipping_address_1'  => '123 Mango St',
            'shipping_barangay'   => 'Alegria',
            'shipping_city'       => 'Cebu City',
            'shipping_state'      => 'Cebu',
            'shipping_postcode'   => '6000',
            'shipping_phone'      => '09171234567',
            'payment_method'      => 'bank_transfer',
            'subtotal'            => 1000,
            'shipping_cost'       => 100,
            'total'               => 1100,
        ]);
    }

    private function estimateUrl(Order $order): string
    {
        return '/' . $this->prefix . '/extensions/courier-fulfillment/' . $order->id . '/estimate';
    }

    private function serviceabilityUrl(Order $order): string
    {
        return '/' . $this->prefix . '/extensions/courier-fulfillment/' . $order->id . '/serviceability';
    }

    /**
     * The exact 200 shape QuadX staging returned on 2026-08-19 for
     * Quezon City -> Cebu City, list and net side by side.
     */
    private function quadxRates(?float $insuranceFee = null): array
    {
        $attributes = [
            'service_type' => 'next_day',
            'shipping_fee' => [
                'box' => 490, 'big-pouch' => 426, 'oversized' => 650,
                'small-pouch' => 202, 'medium-pouch' => 314,
            ],
            'fees_adjustments' => [
                'shipping_fee' => [
                    'box' => 450, 'big-pouch' => 392.4, 'oversized' => 594,
                    'small-pouch' => 190.8, 'medium-pouch' => 291.6,
                ],
                'applied_adjustments' => [
                    ['code' => 'FUEL_SURCHARGE', 'type' => 'surcharge', 'amount' => ['value' => 10, 'type' => 'fixed']],
                    ['code' => 'PRICE_DROP_Q2_2026', 'type' => 'price_drop', 'amount' => ['value' => 10, 'type' => 'percent']],
                ],
            ],
            'tiered_by' => 'parcel',
        ];

        // QuadX emits this key ONLY when declared_value was sent.
        if ($insuranceFee !== null) {
            $attributes['insurance_fee'] = $insuranceFee;
        }

        return ['data' => ['attributes' => $attributes]];
    }

    /** A store whose rates carry no campaign: the map QuadX bills is the only map it sends. */
    private function quadxRatesWithoutAdjustments(): array
    {
        return ['data' => ['attributes' => ['shipping_fee' => [
            'box' => 490, 'big-pouch' => 426, 'oversized' => 650,
            'small-pouch' => 202, 'medium-pouch' => 314,
        ]]]];
    }

    /**
     * Money on the wire, compared as a number.
     *
     * json_encode() renders a whole float without its decimal point (450.0
     * becomes 450), so the response cannot carry the difference between an
     * int and a float and a strict assertion on one would be testing PHP's
     * encoder rather than the fee.
     */
    private function assertMoney($response, string $path, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, (float) data_get($response->json(), $path), 0.001, "Expected {$path} to be {$expected}.");
    }

    private function bookingContext(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
    }

    // --- the net fee is the only fee ------------------------------------

    public function test_the_estimate_quotes_the_net_fee_not_the_list_price(): void
    {
        $this->bookingContext();
        Http::fake([self::RATES => Http::response($this->quadxRates(), 200)]);

        $response = $this->postJson($this->estimateUrl($this->makeOrder()), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel'  => 'box',
        ])->assertOk()->assertJsonPath('ok', true);

        // 450 is what QuadX bills. 490 is the pre-adjustment list price and
        // must never reach a screen an operator pays money from.
        $this->assertMoney($response, 'shipping', 450.0);
        $this->assertMoney($response, 'total', 450.0);
        // `amount` still means the shipping fee, for the ERP that reads it.
        $this->assertMoney($response, 'amount', 450.0);
    }

    public function test_the_net_fee_reaches_the_parcel_labels_and_the_from_price_too(): void
    {
        $this->bookingContext();
        Http::fake([self::RATES => Http::response($this->quadxRates(), 200)]);

        // One quote answers both "will you carry it" and "for how much", so
        // the coverage card cannot advertise a price the total contradicts.
        $this->getJson($this->serviceabilityUrl($this->makeOrder()))
            ->assertOk()
            ->assertJsonPath('couriers.quadx.status', 'serviceable')
            // Whole pesos arrive from QuadX as JSON integers and are carried
            // through untouched; only the total is cast, where it is summed.
            ->assertJsonPath('couriers.quadx.fees.box', 450)
            ->assertJsonPath('couriers.quadx.from', 190.8);
    }

    public function test_a_quote_with_no_adjustments_falls_back_to_the_only_map_it_was_sent(): void
    {
        $this->bookingContext();
        Http::fake([self::RATES => Http::response($this->quadxRatesWithoutAdjustments(), 200)]);

        $response = $this->postJson($this->estimateUrl($this->makeOrder()), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel'  => 'box',
        ])->assertOk();

        $this->assertMoney($response, 'shipping', 490.0);
    }

    // --- insurance is quoted, never guessed -----------------------------

    public function test_insuring_asks_quadx_for_the_declared_value_and_adds_its_own_figure(): void
    {
        $this->bookingContext();
        // 1% of (1100 - 500) = 6, QuadX's own arithmetic, echoed back to us.
        Http::fake([self::RATES => Http::response($this->quadxRates(6), 200)]);

        $response = $this->postJson($this->estimateUrl($this->makeOrder()), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel'  => 'box',
            'insure'  => 1,
        ])->assertOk();

        $this->assertMoney($response, 'shipping', 450.0);
        $this->assertMoney($response, 'insurance', 6.0);
        $this->assertMoney($response, 'total', 456.0);
        // Insurance is reported beside the shipping fee, never folded into it:
        // the ERP's estimate endpoint reads `amount` and must keep its meaning.
        $this->assertMoney($response, 'amount', 450.0);

        // The declared value is the order total, matching what createShipment
        // would send, so the quote describes the shipment that would exist.
        Http::assertSent(function ($request) {
            $sent = $request->data()['data']['attributes'] ?? [];

            return str_contains($request->url(), 'estimates/rates')
                && array_key_exists('declared_value', $sent)
                && (float) $sent['declared_value'] === 1100.0;
        });
    }

    public function test_not_insuring_declares_nothing_and_charges_nothing(): void
    {
        $this->bookingContext();
        Http::fake([self::RATES => Http::response($this->quadxRates(), 200)]);

        $response = $this->postJson($this->estimateUrl($this->makeOrder()), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel'  => 'box',
        ])->assertOk();

        $this->assertMoney($response, 'insurance', 0.0);
        $this->assertMoney($response, 'total', 450.0);

        // Omitting declared_value entirely makes QuadX default it to the order
        // total and auto-insure. 0 is sent explicitly for the same reason
        // createShipment() sends it.
        Http::assertSent(function ($request) {
            $sent = $request->data()['data']['attributes'] ?? [];

            return str_contains($request->url(), 'estimates/rates')
                && array_key_exists('declared_value', $sent)
                && (float) $sent['declared_value'] === 0.0;
        });
    }

    /**
     * An absent insurance_fee on an insured quote is QuadX declining to price
     * it, not pricing it at zero. The shipping figure still stands on its own,
     * so the total shows shipping alone rather than a number we made up.
     */
    public function test_an_insured_quote_quadx_did_not_price_adds_no_invented_fee(): void
    {
        $this->bookingContext();
        Http::fake([self::RATES => Http::response($this->quadxRates(), 200)]);

        $response = $this->postJson($this->estimateUrl($this->makeOrder()), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel'  => 'box',
            'insure'  => 1,
        ])->assertOk();

        $this->assertMoney($response, 'insurance', 0.0);
        $this->assertMoney($response, 'total', 450.0);
    }

    /**
     * The modal opens with fees quoted from the store's DEFAULT origin. If the
     * operator then picks a different pickup address, the total re-quotes from
     * that one, and the parcel dropdown has to follow it: a parcel labelled
     * from one origin beside a total from another reads as a bug and is one.
     */
    public function test_the_estimate_returns_every_parcel_priced_for_the_origin_it_used(): void
    {
        $this->bookingContext();
        Http::fake([self::RATES => Http::response($this->quadxRates(), 200)]);

        $response = $this->postJson($this->estimateUrl($this->makeOrder()), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel'  => 'box',
        ])->assertOk();

        $this->assertMoney($response, 'fees.box', 450.0);
        $this->assertMoney($response, 'fees.small-pouch', 190.8);
    }

    /**
     * The booking modal builds every action URL from one base, and the
     * one-home re-mount (2026-08-19) left the old `sales/fulfillment` path as
     * a GET-only 301. Every GET kept working by following the redirect, so the
     * breakage was invisible until a POST (book, book-manual, track, estimate)
     * came back 405. This asserts the base is the module's real home, which is
     * the only version of it that accepts a POST.
     */
    public function test_the_booking_modal_posts_to_a_base_that_accepts_posts(): void
    {
        $this->bookingContext();
        $this->makeOrder();

        $page = $this->get('/' . $this->prefix . '/extensions/courier-fulfillment')->assertOk();

        $this->assertStringContainsString(
            "urlBase: '" . url($this->prefix . '/extensions/courier-fulfillment') . "'",
            $page->getContent(),
            'The modal must build its POST URLs from the module home, not the legacy redirect.'
        );
    }

    // --- the total still refuses to invent a figure ---------------------

    public function test_a_parcel_quadx_did_not_price_yields_no_total_at_all(): void
    {
        $this->bookingContext();
        Http::fake([self::RATES => Http::response($this->quadxRates(), 200)]);

        $this->postJson($this->estimateUrl($this->makeOrder()), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel'  => 'refrigerator',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false);
    }

    public function test_an_unserviceable_address_yields_no_total(): void
    {
        $this->bookingContext();
        Http::fake([self::RATES => Http::response(['errors' => ['detail' => 'Delivery address is not serviceable.']], 422)]);

        $this->postJson($this->estimateUrl($this->makeOrder()), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel'  => 'box',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false);
    }
}
