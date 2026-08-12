<?php

namespace Tests\Feature\AdminV2;

use App\Models\Country;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\PlacementMap;
use Extensions\CourierFulfillment\ServiceabilityService;
use Extensions\CourierFulfillment\Support\ServiceabilityVerdict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeCourierFulfillment;
use Tests\Support\SlowCourierFulfillment;
use Tests\TestCase;

/**
 * Owner directive, 2026-08-12 ("the booking modal says who will carry it
 * before you choose"): every courier is asked whether it delivers to this
 * order's address BEFORE the operator picks one, and each answers into one of
 * four card states.
 *
 * The distinction this file exists to protect is the fourth state. A courier
 * we could not REACH has not refused anything, and reporting it as
 * unserviceable would make the modal lie during an outage, which is not
 * hypothetical, since SPX's UAT host stopped resolving in DNS. Several tests
 * below assert specifically that an unreachable courier comes back `unknown`
 * and stays clickable.
 *
 * Http::preventStrayRequests() everywhere: a fake whose URL pattern misses
 * would otherwise fall through to a REAL courier call.
 */
class FulfillmentServiceabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
        Http::preventStrayRequests();
    }

    /** CourierManager's registry is process-global static state. */
    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(CourierManager::class, 'registered');
        $ref->setAccessible(true);
        $registered = $ref->getValue();
        unset($registered['fake'], $registered['slowpoke']);
        $ref->setValue(null, $registered);

        parent::tearDown();
    }

    // --- fixtures ------------------------------------------------------

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

    /** Flag on, credentials absent: the "Not configured" card, which is never asked anything. */
    private function enableSpxFlagOnly(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
    }

    /** Fully configured SPX, including the account pair every order endpoint needs. */
    private function enableSpx(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'spx_mode', 'value' => 'uat']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => Crypt::encryptString('fake-user-secret-not-real')]);
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

    /** An order sitting where booking starts (To Pack), bound for a real PH barangay. */
    private function makeOrder(): Order
    {
        $status = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Processing'],
            ['sort_order' => 1, 'is_default' => true],
        );
        PlacementMap::put($status->id, 'to_pack');

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
            'shipping_city'       => 'Caluya',
            'shipping_state'      => 'Antique',
            'shipping_postcode'   => '5711',
            // A valid PH mobile, so SPX's own payload builder succeeds and
            // the group tests below exercise its classifier rather than its
            // incomplete-address guard.
            'shipping_phone'      => '09171234567',
            'payment_method'      => 'bank_transfer',
            'subtotal'            => 1000,
            'shipping_cost'       => 100,
            'total'               => 1100,
        ]);
    }

    private function serviceabilityUrl(Order $order): string
    {
        return '/' . $this->prefix . '/sales/fulfillment/' . $order->id . '/serviceability';
    }

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/sales/fulfillment';
    }

    /** QuadX's rate endpoint, the one call behind both the fee and the coverage answer. */
    private const RATES = 'api.staging.quadx.xyz/v2/orders/estimates/rates';

    /** The exact 200 body the owner verified against QuadX staging. */
    private function quadxServiceable(): array
    {
        return ['data' => ['attributes' => ['shipping_fee' => [
            'box' => 300, 'big-pouch' => 120, 'oversized' => 550,
            'small-pouch' => 80, 'medium-pouch' => 100,
        ]]]];
    }

    /** The exact 422 body the owner verified with Caluya, Antique. */
    private function quadxRefusal(): array
    {
        return ['errors' => ['detail' => 'Delivery address is not serviceable.']];
    }

    // --- state 1: available --------------------------------------------

    public function test_a_courier_that_serves_the_address_answers_serviceable_with_a_from_price(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake([self::RATES => Http::response($this->quadxServiceable(), 200)]);

        $order = $this->makeOrder();

        $this->getJson($this->serviceabilityUrl($order))
            ->assertOk()
            ->assertJsonPath('couriers.quadx.status', 'serviceable')
            // The from-price is the CHEAPEST parcel, not the first one
            // listed and not an average.
            ->assertJsonPath('couriers.quadx.from', 80)
            ->assertJsonPath('couriers.quadx.fees.box', 300)
            ->assertJsonPath('all_refused', false);
    }

    // --- state 3: not serviceable ---------------------------------------

    public function test_a_refusal_is_reported_as_not_serviceable_in_the_couriers_own_words(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake([self::RATES => Http::response($this->quadxRefusal(), 422)]);

        $order = $this->makeOrder();

        $this->getJson($this->serviceabilityUrl($order))
            ->assertOk()
            ->assertJsonPath('couriers.quadx.status', 'not_serviceable')
            // A recognised failure, so the modal writes its own sentence
            // naming the destination and never prints QuadX's words beside
            // it. The words are still carried, verbatim, for the case where
            // they ARE what gets shown.
            ->assertJsonPath('couriers.quadx.kind', 'destination')
            ->assertJsonPath('couriers.quadx.reason', 'Delivery address is not serviceable.')
            ->assertJsonPath('couriers.quadx.from', null);
    }

    /**
     * Owner ruling, 2026-08-12: show the courier's raw text ONLY when we do
     * not recognise the failure. An unrecognised 4xx is also not a refusal,
     * because we do not know that it was about the address at all.
     */
    public function test_an_unrecognised_failure_carries_the_couriers_own_words_and_is_not_a_refusal(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake([self::RATES => Http::response(['errors' => ['detail' => 'service_type is invalid']], 422)]);

        $order = $this->makeOrder();

        $this->getJson($this->serviceabilityUrl($order))
            ->assertOk()
            ->assertJsonPath('couriers.quadx.status', 'unknown')
            ->assertJsonPath('couriers.quadx.kind', null)
            ->assertJsonPath('couriers.quadx.reason', 'service_type is invalid');
    }

    /** A transport failure has no courier words at all, so none are invented or shown. */
    public function test_a_transport_failure_carries_no_courier_words(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake([self::RATES => fn () => throw new ConnectionException('cURL error 6: Could not resolve host')]);

        $order = $this->makeOrder();

        $this->getJson($this->serviceabilityUrl($order))
            ->assertOk()
            ->assertJsonPath('couriers.quadx.status', 'unknown')
            ->assertJsonPath('couriers.quadx.kind', 'unreachable')
            // curl's message is ours, not QuadX's, and an operator never sees it.
            ->assertJsonPath('couriers.quadx.reason', null);
    }

    // --- state 4: could not check ---------------------------------------
    // The load-bearing one. Each of these would be a LIE if it came back as
    // a refusal.

    public function test_an_unreachable_courier_is_never_reported_as_unserviceable(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        // Exactly what a dead DNS record produces (SPX's UAT host today) and
        // what a connect timeout produces: no response at all.
        Http::fake([self::RATES => fn () => throw new ConnectionException('cURL error 6: Could not resolve host')]);

        $order = $this->makeOrder();

        $response = $this->getJson($this->serviceabilityUrl($order))->assertOk();

        $response->assertJsonPath('couriers.quadx.status', 'unknown');
        $this->assertNotSame('not_serviceable', $response->json('couriers.quadx.status'));
        // Nobody refused anything, so the all-refused line must not appear.
        $response->assertJsonPath('all_refused', false);
    }

    public function test_a_courier_slower_than_the_budget_resolves_to_could_not_check(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // A courier that answers, but too late. The verdict it eventually
        // produced is discarded: the operator has already waited longer than
        // the packing desk can afford, and the honest report of that is
        // "could not check", never the serviceable answer that arrived after
        // the budget.
        CourierManager::register(SlowCourierFulfillment::class);
        Setting::create(['group' => 'courier', 'key' => 'slowpoke_enabled', 'value' => '1']);

        $order = $this->makeOrder();

        $started = microtime(true);
        $verdict = ServiceabilityService::forCourier(new SlowCourierFulfillment(), $order);

        $this->assertSame(ServiceabilityVerdict::UNKNOWN, $verdict->status);
        $this->assertSame(ServiceabilityVerdict::KIND_UNREACHABLE, $verdict->kind);
        // The double really did overrun the budget (guards against a green
        // test from a double that returned instantly).
        $this->assertGreaterThan(ServiceabilityService::TIMEOUT_SECONDS, microtime(true) - $started);
    }

    public function test_a_courier_that_cannot_answer_in_advance_defaults_to_unknown(): void
    {
        $this->installExtension();
        // The sanctioned default: FakeCourierFulfillment writes no
        // serviceability code at all, it just uses the trait.
        $order = $this->makeOrder();

        $this->assertSame(
            ServiceabilityVerdict::UNKNOWN,
            (new FakeCourierFulfillment())->checkServiceability($order)->status
        );
    }

    // --- SPX, classified by group ----------------------------------------
    // Owner ruling, 2026-08-12: group, not individual code, so a code nobody
    // has seen yet still lands somewhere sensible.

    /**
     * @param int    $code     the SPX ret_code in data.fail_list
     * @param string $status   the verdict it must produce
     * @param ?string $kind    the group it must land in
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('spxCodeGroups')]
    public function test_spx_codes_are_classified_by_group(int $code, string $status, ?string $kind): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configurePickupOrigin();
        Http::fake(['*/batch_check_order' => Http::response([
            'ret_code' => 0,
            'message'  => 'success. ',
            'data'     => ['fail_list' => [['ret_code' => $code, 'message' => 'SPX said something about ' . $code, 'order_id' => '1']]],
        ], 200)]);

        $order = $this->makeOrder();

        $this->getJson($this->serviceabilityUrl($order))
            ->assertOk()
            ->assertJsonPath('couriers.spx.status', $status)
            ->assertJsonPath('couriers.spx.kind', $kind);
    }

    public static function spxCodeGroups(): array
    {
        return [
            // Refusals: the card goes inert, and our own sentence names what
            // was refused.
            'destination not covered'     => [10026, 'not_serviceable', 'destination'],
            'destination, unseen sibling' => [10033, 'not_serviceable', 'destination'],
            'origin not covered'          => [10025, 'not_serviceable', 'origin'],
            'COD not supported there'     => [10030, 'not_serviceable', 'cod'],
            'postcode invalid'            => [10027, 'not_serviceable', 'postcode'],
            // NOT refusals: none of these says the address cannot be served.
            'parcel refused'              => [10001, 'unknown', 'parcel'],
            'COD amount refused'          => [10003, 'unknown', 'parcel'],
            'pickup slot'                 => [10038, 'unknown', 'slot'],
            'their side failed'           => [10044, 'unknown', 'their_side'],
            // A code from no group at all: still never a refusal.
            'unrecognised'                => [19999, 'unknown', null],
        ];
    }

    public function test_an_spx_side_failure_is_never_reported_as_unserviceable(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableSpx();
        $this->configurePickupOrigin();
        // 10044 is SPX's own machinery failing. It is an outage, and belongs
        // in the same bucket as a dead DNS record.
        Http::fake(['*/batch_check_order' => Http::response([
            'ret_code' => 10044, 'message' => 'system error', 'data' => [],
        ], 200)]);

        $order = $this->makeOrder();

        $response = $this->getJson($this->serviceabilityUrl($order))->assertOk();
        $this->assertSame('unknown', $response->json('couriers.spx.status'));
        $this->assertSame('their_side', $response->json('couriers.spx.kind'));
        $response->assertJsonPath('all_refused', false);
    }

    // --- the all-refused line -------------------------------------------

    public function test_all_refused_is_true_only_when_every_courier_we_could_ask_refused(): void
    {
        $refusal = ServiceabilityVerdict::notServiceable(ServiceabilityVerdict::KIND_DESTINATION, 'Delivery address is not serviceable.');

        $this->assertTrue(ServiceabilityService::allRefused(['quadx' => $refusal, 'spx' => $refusal]));
        // One courier we could not reach is not a refusal, so the line must
        // not claim nobody delivers here.
        $this->assertFalse(ServiceabilityService::allRefused([
            'quadx' => $refusal, 'spx' => ServiceabilityVerdict::unknown(ServiceabilityVerdict::KIND_UNREACHABLE),
        ]));
        $this->assertFalse(ServiceabilityService::allRefused([
            'quadx' => $refusal, 'spx' => ServiceabilityVerdict::serviceable(['box' => 120]),
        ]));
        // Nobody to ask is not a refusal either.
        $this->assertFalse(ServiceabilityService::allRefused([]));
    }

    public function test_the_endpoint_reports_all_refused_when_the_only_courier_says_no(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake([self::RATES => Http::response($this->quadxRefusal(), 422)]);

        $order = $this->makeOrder();

        $this->getJson($this->serviceabilityUrl($order))
            ->assertOk()
            ->assertJsonPath('all_refused', true);
    }

    // --- who gets asked, and how often -----------------------------------

    public function test_an_unconfigured_courier_is_never_asked(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->enableSpxFlagOnly();
        $this->configurePickupOrigin();
        Http::fake([self::RATES => Http::response($this->quadxServiceable(), 200)]);

        $order = $this->makeOrder();

        $response = $this->getJson($this->serviceabilityUrl($order))->assertOk();

        // SPX has a card, but it is the "Not configured" one: there are no
        // credentials to ask with, and a second refusal on top of that would
        // tell the operator nothing.
        $response->assertJsonPath('couriers.quadx.status', 'serviceable');
        $this->assertArrayNotHasKey('spx', $response->json('couriers'));
        // Http::preventStrayRequests() would have failed the test if SPX had
        // been called at all.
    }

    public function test_the_verdict_is_reused_for_the_session_rather_than_asked_again(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake([self::RATES => Http::response($this->quadxRefusal(), 422)]);

        $order = $this->makeOrder();

        $this->getJson($this->serviceabilityUrl($order))->assertOk();
        $this->getJson($this->serviceabilityUrl($order))->assertOk()
            ->assertJsonPath('couriers.quadx.status', 'not_serviceable');

        // Asked once. The cache is the session and nothing else: coverage
        // moves, and a stale refusal persisted against an address is worse
        // than an extra call.
        Http::assertSentCount(1);
    }

    public function test_the_queue_page_itself_asks_no_courier_anything(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake();

        $this->makeOrder();

        $this->get($this->queueUrl())->assertOk();

        // The check belongs to the modal opening, never to the page load: an
        // admin who only glances at the queue must never wait on a courier.
        Http::assertNothingSent();
    }

    // --- the four cards --------------------------------------------------

    public function test_the_courier_step_renders_four_states_across_up_to_four_columns(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->enableSpxFlagOnly();
        $this->configurePickupOrigin();
        // The store keeps a hand-arranged courier, which is what puts the
        // manual card on the same row as the integrations.
        \App\Models\ShippingCourier::create(['store_id' => 1, 'name' => 'LBC Express', 'is_active' => true, 'sort_order' => 1]);
        Http::fake();

        $this->makeOrder();

        $response = $this->get($this->queueUrl())->assertOk();

        // Columns, not rows.
        $response->assertSee('v-choice v-choice--cols', false);

        // One card per state, each gated on the verdict for that courier.
        $response->assertSee('data-courier-state="serviceable"', false)
            ->assertSee('data-courier-state="unknown"', false)
            ->assertSee('data-courier-state="not_serviceable"', false)
            ->assertSee('data-courier-configured="false"', false)
            ->assertSee('Not configured')
            ->assertSee('Not serviceable')
            ->assertSee('Could not check');

        // The state is a WORD every time, carried by the same element that
        // is styled: grey alone never says which state a card is in.
        $response->assertSee('v-choice__state', false);

        // The refusal genuinely cannot advance (no handler at all), and the
        // manual choice is still last and on the same footing.
        $response->assertDontSee('data-courier-state="not_serviceable" x-show="verdict(\'quadx\') === \'not_serviceable\'" @click', false);
        $response->assertSee('data-courier-card="__manual"', false);
    }

    public function test_a_courier_we_could_not_check_is_still_clickable(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake();

        $this->makeOrder();

        $response = $this->get($this->queueUrl())->assertOk();

        // A real <button> wired to chooseCourier, not a disabled one and not
        // a <div>: a network timeout is not a refusal, so the operator can
        // still go and ask the courier by booking.
        $response->assertSee(
            '<button type="button" class="v-choice__card v-choice__card--caution" data-courier-card="quadx" data-courier-state="unknown" x-show="verdict(\'quadx\') === \'unknown\'" @click="chooseCourier(\'quadx\')">',
            false
        );
    }

    public function test_the_modal_names_the_wait_and_the_place_it_is_waiting_on(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->configurePickupOrigin();
        Http::fake();

        $this->makeOrder();

        $response = $this->get($this->queueUrl())->assertOk();

        // Not a bare spinner: who is being asked, and about where.
        // (Both payloads below are Js::from output, so the quotes around
        // each value are hex-escaped on the wire.)
        $response->assertSee('v-choice__wait', false)
            ->assertSee('waitLine', false)
            ->assertSee('checkedCouriers', false)
            ->assertSee('QuadX', false)
            // The destination travels with the row, so both the wait line
            // and the refusal sentence can name it.
            ->assertSee('destination', false)
            ->assertSee('Alegria, Caluya, Antique', false);
    }
}
