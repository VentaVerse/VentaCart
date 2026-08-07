<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\StatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeCourierFulfillment;
use Tests\TestCase;

/**
 * Task 4 of the fulfillment-polish plan: schema-driven per-courier settings.
 * See docs/superpowers/plans/2026-08-08-fulfillment-polish.md (Task 4) and
 * extensions/courier-fulfillment/src/Contracts/CourierFulfillmentInterface.php's
 * settingsSchema() docblock for the contract this exercises.
 *
 * Task 5 additions (courier-agnostic status + tracking): StatusMapper reading
 * "{courier}_status_map" derived from the courier key, the queue page looping
 * CourierFulfillmentInterface::trackingLinks() rather than parsing
 * courier_meta itself, and the settings page's Test Connection endpoint
 * taking a {courier} route parameter — all proven with FakeCourierFulfillment
 * so a second real courier (not just QuadX) is genuinely supported.
 */
class FulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
    }

    /**
     * CourierManager's registry is process-global static state (see its own
     * docblock convention + ExtensionManager::registerAutoloader()'s same
     * caveat) — any courier a test registers must be un-registered after,
     * or it leaks into every later test in this run.
     */
    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(CourierManager::class, 'registered');
        $ref->setAccessible(true);
        $registered = $ref->getValue();
        unset($registered['fake']);
        $ref->setValue(null, $registered);

        parent::tearDown();
    }

    private function settingsUrl(string $suffix = ''): string
    {
        return '/' . $this->prefix . '/settings/modules/courier-fulfillment' . $suffix;
    }

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/sales/fulfillment';
    }

    /**
     * Raw URL strings, not route(admin.fulfillment.*, ...) — this extension's
     * routes are registered dynamically by App\Http\Middleware\BootExtensions
     * on the FIRST request after installExtension() (see that middleware's
     * docblock), so route() would throw RouteNotFoundException if called
     * before any request has gone through the HTTP kernel. queueUrl()/
     * settingsUrl() above already follow this convention.
     */
    private function bookUrl(Order $order): string
    {
        return $this->queueUrl() . '/' . $order->id . '/book';
    }

    private function cancelUrl(Order $order): string
    {
        return $this->queueUrl() . '/' . $order->id . '/cancel';
    }

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
    }

    // ------------------------------------------------------------------
    // Task 6 helpers — QuadX credentials (staging), a bookable order with
    // the shipping fields buildPayload() reads, and an ERP bearer token.
    // ------------------------------------------------------------------

    /** Turns QuadX "on" (enabled + configured) against the staging base URL, so CourierManager::enabled() includes it and FulfillmentService::book() proceeds past the courier_unavailable guard. */
    private function enableQuadxStaging(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);
    }

    /** Cebu (non-NCR) address deliberately, so QuadXLocationResolver's NCR "City" suffix rewriting never kicks in and payload assertions can compare raw strings. */
    private function makeBookableOrder(array $overrides = []): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);

        return Order::create(array_merge([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+639171234567',
            'shipping_first_name' => 'Jane',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Mango St',
            'shipping_city' => 'Cebu City',
            'shipping_state' => 'Cebu',
            'shipping_postcode' => '6000',
            'shipping_phone' => '+639171234567',
            'payment_method' => 'bank_transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ], $overrides));
    }

    /** Stores an encrypted ERP token under settings(group=api,key=erp_token) — the exact row App\Http\Middleware\ErpApiAuth reads — and returns the plaintext for the Bearer header. */
    private function makeErpToken(string $token = 'erp-test-token-abc123'): string
    {
        Setting::create(['group' => 'api', 'key' => 'erp_token', 'value' => Crypt::encryptString($token)]);
        return $token;
    }

    /**
     * $this->artisan() reuses the console Kernel's memoized Illuminate\Console\
     * Application instance (Kernel::getArtisan() caches it in $this->artisan),
     * and that instance is normally constructed the FIRST time any artisan
     * command runs — which, under RefreshDatabase, is the framework's own
     * internal `migrate` call during this test's setUp(), well before
     * installExtension() below ever runs. ServiceProvider::commands()
     * registers new commands via an Artisan::starting() bootstrap closure
     * that only fires when a *new* Console\Application is constructed, so a
     * provider booted mid-test (as installExtension() does, via
     * ExtensionManager::bootEnabled()) is too late — the console app already
     * exists. registerCommand() adds directly to that already-built instance
     * instead, sidestepping the ordering problem entirely.
     */
    private function registerPollCommand(): void
    {
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new \Extensions\CourierFulfillment\Console\Commands\PollCourierStatus());
    }

    // ------------------------------------------------------------------
    // Save round-trip through the derived allowlist.
    // ------------------------------------------------------------------

    public function test_settings_save_round_trips_through_the_derived_allowlist(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl(), [
            'settings' => [
                'quadx_enabled' => '1',
                'quadx_mode' => 'production',
                'quadx_default_service' => 'same_day_pickup',
                'quadx_default_parcel' => 'box',
                'fulfillment_enabled' => '1',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'production']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_default_service', 'value' => 'same_day_pickup']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_default_parcel', 'value' => 'box']);
    }

    public function test_unknown_keys_are_discarded(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl(), [
            'settings' => [
                'quadx_enabled' => '1',
                'not_a_real_setting' => 'sneaky',
                'quadx_webhook_token' => 'attacker-supplied-token',
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'not_a_real_setting']);
        // Server-generated, never writable via this form even under its real key.
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_webhook_token', 'value' => 'attacker-supplied-token']);
    }

    // ------------------------------------------------------------------
    // Encrypted mask + blank-keeps.
    // ------------------------------------------------------------------

    public function test_encrypted_field_is_encrypted_at_rest_and_masked_on_the_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl(), [
            'settings' => ['quadx_staging_api_key' => 'super-secret-key-1234'],
        ])->assertRedirect();

        $stored = Setting::where('group', 'courier')->where('key', 'quadx_staging_api_key')->value('value');
        $this->assertNotSame('super-secret-key-1234', $stored);
        $this->assertSame('super-secret-key-1234', Crypt::decryptString($stored));

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('1234') // last 4 of the mask
            ->assertDontSee('super-secret-key-1234');
    }

    public function test_blank_submission_keeps_the_stored_encrypted_value(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl(), [
            'settings' => ['quadx_staging_api_key' => 'original-secret'],
        ])->assertRedirect();
        $original = Setting::where('group', 'courier')->where('key', 'quadx_staging_api_key')->value('value');

        // Re-save with the field left blank (nothing typed to change it).
        $this->post($this->settingsUrl(), [
            'settings' => ['quadx_staging_api_key' => '', 'quadx_mode' => 'staging'],
        ])->assertRedirect();

        $after = Setting::where('group', 'courier')->where('key', 'quadx_staging_api_key')->value('value');
        $this->assertSame($original, $after);
        $this->assertSame('original-secret', Crypt::decryptString($after));
    }

    // ------------------------------------------------------------------
    // Second-courier proof: a courier that isn't QuadX renders + saves
    // through the exact same generic path, independently of QuadX's keys.
    // ------------------------------------------------------------------

    public function test_a_second_registered_courier_renders_its_own_card_and_saves_independently(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        $response = $this->get($this->settingsUrl());
        $response->assertOk()
            ->assertSee('Fake Courier')                                  // card title, from getName()
            ->assertSee('name="settings[fake_enabled]"', false)
            ->assertSee('name="settings[fake_mode]"', false)
            ->assertSee('name="settings[fake_staging_token]"', false)
            ->assertSee('name="settings[fake_production_token]"', false)
            ->assertSee('name="settings[fake_note]"', false);

        $this->post($this->settingsUrl(), [
            'settings' => [
                // QuadX keys and fake_ keys posted together, proving neither
                // allowlist/encryption pass touches the other courier's data.
                'quadx_enabled' => '1',
                'quadx_staging_api_key' => 'quadx-secret',
                'fake_enabled' => '1',
                'fake_mode' => 'production',
                'fake_note' => 'hello from fake',
                'fake_staging_token' => 'fake-staging-secret',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'fake_enabled', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'fake_mode', 'value' => 'production']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'fake_note', 'value' => 'hello from fake']);

        $quadxSecret = Setting::where('group', 'courier')->where('key', 'quadx_staging_api_key')->value('value');
        $fakeSecret = Setting::where('group', 'courier')->where('key', 'fake_staging_token')->value('value');
        $this->assertSame('quadx-secret', Crypt::decryptString($quadxSecret));
        $this->assertSame('fake-staging-secret', Crypt::decryptString($fakeSecret));
        $this->assertNotSame($quadxSecret, $fakeSecret);
    }

    // ------------------------------------------------------------------
    // fulfillment_enabled — opt-out "pause" toggle (fixed per review):
    // missing/NULL means active (installing+enabling the extension IS the
    // admin's intent signal), only an explicit '0' pauses the queue, and
    // pausing renders an explanatory empty state — never a redirect trap.
    // ------------------------------------------------------------------

    public function test_queue_page_is_reachable_by_default_when_fulfillment_enabled_is_unset(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        // No 'fulfillment_enabled' row written at all — NULL-means-on.
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'fulfillment_enabled']);

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertDontSee('Fulfillment is paused')
            ->assertSee('To Book'); // the real queue's filter tabs render
    }

    public function test_queue_page_is_reachable_when_fulfillment_enabled_is_explicitly_one(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'fulfillment_enabled'], ['value' => '1']);

        $this->get($this->queueUrl())->assertOk()->assertDontSee('Fulfillment is paused');
    }

    public function test_queue_page_shows_an_explanatory_paused_state_when_explicitly_disabled(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'fulfillment_enabled'], ['value' => '0']);

        $response = $this->get($this->queueUrl());

        // 200, never a redirect — a paused page explains itself instead of
        // bouncing the admin away.
        $response->assertOk()
            ->assertSee('Fulfillment is paused')
            ->assertSee('Go to Courier Settings')
            ->assertDontSee('To Book'); // the real queue's filter tabs are absent
    }

    public function test_settings_page_is_reachable_even_while_fulfillment_is_paused(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'fulfillment_enabled'], ['value' => '0']);
        // Paused must never lock an admin out of turning it back on.

        $this->get($this->settingsUrl())->assertOk();
    }

    // ------------------------------------------------------------------
    // Task 5: StatusMapper reads "{courier}_status_map" derived from the
    // courier key — proven with a second, non-QuadX key so this is genuinely
    // courier-agnostic, not just correct for the one key it shipped with.
    // ------------------------------------------------------------------

    public function test_status_mapper_reads_the_map_under_the_bound_couriers_own_key(): void
    {
        // courier_provider etc. are columns the extension's own migration adds —
        // install it so they exist on this test's in-memory schema.
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        $pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $delivered = OrderStatus::create(['store_id' => 1, 'name' => 'Delivered', 'sort_order' => 2]);

        // Two different couriers' maps, saved under their own derived keys —
        // proves no cross-talk between "fake_status_map" and "quadx_status_map".
        Setting::create(['group' => 'courier', 'key' => 'fake_status_map', 'value' => json_encode([
            'delivered' => ['order_status_id' => $delivered->id, 'notify' => false],
        ])]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_status_map', 'value' => json_encode([
            'delivered' => ['order_status_id' => $pending->id, 'notify' => false], // deliberately different, to prove isolation
        ])]);

        $this->assertSame(
            ['order_status_id' => $delivered->id, 'notify' => false],
            StatusMapper::resolve(StatusMapper::currentMap('fake'), 'delivered')
        );
        $this->assertSame(
            ['order_status_id' => $pending->id, 'notify' => false],
            StatusMapper::resolve(StatusMapper::currentMap('quadx'), 'delivered')
        );
        $this->assertSame([], StatusMapper::currentMap('unregistered-courier'));

        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $pending->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
        ]);
        $order->forceFill(['courier_provider' => 'fake'])->save();

        // apply() derives the courier key from the order itself, so booking
        // with the fake courier moves the order using ITS map, not QuadX's.
        $this->assertTrue(StatusMapper::apply($order, 'delivered'));
        $this->assertSame($delivered->id, $order->fresh()->order_status_id);
    }

    // ------------------------------------------------------------------
    // Task 5: tracking-link extraction lives behind the interface — the
    // queue page loops CourierFulfillmentInterface::trackingLinks() rather
    // than parsing any one courier's courier_meta shape itself.
    // ------------------------------------------------------------------

    public function test_queue_page_renders_a_tracking_link_from_a_non_quadx_courier(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 1, 'is_default' => true]);
        $order = Order::create([
            'store_id' => 1,
            'order_status_id' => $status->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
        ]);
        $order->forceFill([
            'courier_provider' => 'fake',
            'courier_tracking_number' => 'FAKE-123',
            'courier_status' => 'in_transit',
        ])->save();

        $this->get($this->queueUrl() . '?filter=all')
            ->assertOk()
            ->assertSee('https://fake-courier.test/track/' . $order->id, false)
            ->assertSee('FAKE-123');
    }

    // ------------------------------------------------------------------
    // Final-review fix (a): per-row cancelable/phase must derive from EACH
    // row's own resolved courier, never a page-level $primary — a mixed
    // queue with two registered couriers is the only way to catch a
    // regression back to the $primary-for-everyone bug.
    // ------------------------------------------------------------------

    public function test_queue_derives_cancelable_and_phase_from_each_rows_own_courier_not_a_shared_primary(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        // Deliberately different rules at the SAME courier_status so a bug
        // that falls back to $primary (whichever courier happens to be
        // first) is unmistakable: QuadXFulfillment::cancelableStatuses()
        // includes 'for_pickup' and statusPhase('for_pickup') is step 1;
        // FakeCourierFulfillment::cancelableStatuses() is only ['pending']
        // and statusPhase() always returns step 0.
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 1, 'is_default' => true]);

        $quadxOrder = $this->makeBookableOrder(['order_status_id' => $status->id]);
        $quadxOrder->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-MIX-001',
            'courier_status' => 'for_pickup',
        ])->save();

        $fakeOrder = $this->makeBookableOrder(['order_status_id' => $status->id]);
        $fakeOrder->forceFill([
            'courier_provider' => 'fake',
            'courier_tracking_number' => 'FAKE-MIX-001',
            'courier_status' => 'for_pickup',
        ])->save();

        // QuadXFulfillment gets registered by the extension's service
        // provider on this request (see bookUrl()'s docblock on the
        // BootExtensions timing) — no enableQuadxStaging() needed since
        // both orders are already booked (isActivelyBooked() doesn't care
        // whether the courier is currently "enabled").
        $response = $this->get($this->queueUrl() . '?filter=all');
        $response->assertOk();
        $content = $response->getContent();

        // Cancel affordance: QuadX row is cancelable ('for_pickup' is in its
        // list), Fake row is not (only 'pending' is in Fake's list).
        $this->assertStringContainsString('cancel-fulfillment-' . $quadxOrder->id, $content);
        $this->assertStringNotContainsString('cancel-fulfillment-' . $fakeOrder->id, $content);

        // phaseStep in each row's own JSON payload (fed to openDrawer()).
        $quadxFlags = $this->extractRowFlags($content, $quadxOrder->id);
        $fakeFlags = $this->extractRowFlags($content, $fakeOrder->id);

        $this->assertSame(['cancelable' => true, 'phaseStep' => 1], $quadxFlags);
        $this->assertSame(['cancelable' => false, 'phaseStep' => 0], $fakeFlags);
    }

    /**
     * Pulls the {@see openDrawer()} JSON payload for one row out of the
     * rendered queue page and returns just the two fields this fix touches.
     * Js::from() renders an object as `JSON.parse('...')` with a "-escaped
     * inner string (never a literal quote or apostrophe inside the JS string
     * literal), so the captured inner text is itself valid JSON once wrapped
     * in real double quotes — one json_decode() to unescape it back to a
     * JSON string, a second to decode that string into the array.
     */
    private function extractRowFlags(string $html, int $orderId): array
    {
        preg_match_all("/openDrawer\\(JSON\\.parse\\('(.*?)'\\)\\)/", $html, $matches);
        foreach ($matches[1] as $escaped) {
            $jsonString = json_decode('"' . $escaped . '"');
            $decoded = json_decode($jsonString, true);
            if (is_array($decoded) && ($decoded['id'] ?? null) === $orderId) {
                return ['cancelable' => $decoded['cancelable'], 'phaseStep' => $decoded['phaseStep']];
            }
        }
        $this->fail("No row JSON payload found for order #{$orderId}");
    }

    // ------------------------------------------------------------------
    // Task 5: Test Connection is a {courier}-parameterized endpoint — proven
    // against both a courier with real (QuadX) behavior to preserve and one
    // that plainly is not QuadX.
    // ------------------------------------------------------------------

    public function test_test_connection_endpoint_is_parameterized_per_courier(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        // Unconfigured QuadX — same message QuadXFulfillment::testConnection()
        // has always returned when no credentials are saved.
        $this->postJson($this->settingsUrl('/quadx/test-connection'))
            ->assertOk()
            ->assertJson(['ok' => false, 'message' => 'API key/secret not set.']);

        // A different, real second courier — proves the endpoint isn't
        // secretly still hardcoded to CourierManager::get('quadx').
        $this->postJson($this->settingsUrl('/fake/test-connection'))
            ->assertOk()
            ->assertJson(['ok' => false, 'message' => 'not implemented']);

        // Unregistered key — never a 404/500, a clean JSON error instead.
        $this->postJson($this->settingsUrl('/nope/test-connection'))
            ->assertOk()
            ->assertJson(['ok' => false, 'message' => 'Courier not registered.']);
    }

    public function test_settings_page_renders_a_second_couriers_webhook_url_and_status_mapping_table(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        $response = $this->get($this->settingsUrl());
        $response->assertOk()
            // Webhook URL section renders per-courier, generically, not just for QuadX.
            ->assertSee('/webhooks/courier/fake?token=', false)
            // Status-mapping table renders FakeCourierFulfillment::mappableStatuses().
            ->assertSee('name="settings[fake_status_map][pending][order_status_id]"', false)
            ->assertSee('name="settings[fake_status_map][delivered][order_status_id]"', false)
            // Test Connection route is parameterized to this courier's key.
            ->assertSee($this->settingsUrl('/fake/test-connection'), false);
    }

    // ==================================================================
    // Task 6: feature-test hardening over FROZEN behavior.
    // Http::fake ONLY — zero real outbound requests anywhere below.
    // ==================================================================

    // ------------------------------------------------------------------
    // Booking round-trip: payload shape + idempotency + rebook-after-cancel.
    // ------------------------------------------------------------------

    public function test_booking_sends_the_expected_quadx_payload_and_stores_the_tracking_number(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake([
            'api.staging.quadx.xyz/v2/orders' => Http::response([
                'id' => 555,
                'tracking_number' => 'QX-BOOK-001',
                'status' => 'for_pickup',
                'reference_id' => '1',
            ], 200),
        ]);

        $order = $this->makeBookableOrder();

        $response = $this->post($this->bookUrl($order), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($order) {
            if ($request->url() !== 'https://api.staging.quadx.xyz/v2/orders') {
                return false;
            }
            $body = $request->data();

            return $body['reference_id'] === (string) $order->id
                && $body['service'] === 'next_day'
                && $body['shipment'] === 'box'
                // Not COD (payment_method 'bank_transfer') and no buyer_payment
                // override posted from the admin form -> 'other', not 'cod'.
                && $body['payment_method'] === 'other'
                // insure not posted -> declared_value must be 0, never default
                // to the order total (that hazard is called out in buildPayload()).
                && (int) $body['declared_value'] === 0
                && (float) $body['total'] === 1100.0
                && $body['delivery_address']['line_1'] === '123 Mango St'
                && $body['delivery_address']['postal_code'] === '6000'
                && $body['delivery_address']['city'] === 'Cebu City'
                && $body['buyer_name'] === 'Jane Doe'
                // net_off is the "bill me / deduct from remittance" option ->
                // seller_payment_method is omitted entirely, not sent as 'net_off'.
                && !array_key_exists('seller_payment_method', $body);
        });

        $order->refresh();
        $this->assertSame('quadx', $order->courier_provider);
        $this->assertSame('QX-BOOK-001', $order->courier_tracking_number);
        $this->assertSame('for_pickup', $order->courier_status);
        // Core snapshot field — the extension is the only writer of it.
        $this->assertSame('QX-BOOK-001', $order->tracking_number);
    }

    public function test_admin_book_route_blocks_a_double_book_with_a_single_outbound_call_and_a_user_facing_message(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake([
            'api.staging.quadx.xyz/v2/orders' => Http::response(['tracking_number' => 'QX-DUP-001', 'status' => 'for_pickup'], 200),
        ]);

        $order = $this->makeBookableOrder();
        $bookUrl = $this->bookUrl($order);
        $payload = ['courier' => 'quadx', 'service' => 'next_day', 'parcel' => 'box', 'shipping_payment' => 'net_off'];

        $this->post($bookUrl, $payload)->assertSessionHas('success');

        $second = $this->post($bookUrl, $payload);
        $second->assertRedirect();
        $second->assertSessionHas('error');
        $this->assertStringContainsString('already booked', session('error'));

        // The controller's own isActivelyBooked() guard short-circuits before
        // FulfillmentService::book() is ever reached on the second call — one
        // shipment must exist at QuadX, not two.
        Http::assertSentCount(1);
        $this->assertSame('QX-DUP-001', $order->fresh()->courier_tracking_number);
    }

    public function test_erp_book_endpoint_is_idempotent_on_repeat_calls(): void
    {
        // The ERP endpoint has NO controller-level pre-check (unlike the admin
        // route above) — a retry exercises FulfillmentService::book()'s own
        // idempotency guard directly, which is exactly the hazard its docblock
        // describes ("the ERP may retry").
        $this->installExtension();
        $this->enableQuadxStaging();
        $token = $this->makeErpToken();

        Http::fake([
            'api.staging.quadx.xyz/v2/orders' => Http::response(['tracking_number' => 'QX-ERP-001', 'status' => 'for_pickup'], 200),
        ]);

        $order = $this->makeBookableOrder();
        $payload = ['courier' => 'quadx', 'service' => 'next_day', 'parcel' => 'box', 'shipping_payment' => 'net_off'];

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/book-courier", $payload);
        $first->assertOk()->assertJson([
            'success' => true,
            'already_booked' => false,
            'tracking_number' => 'QX-ERP-001',
        ]);

        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/orders/{$order->id}/book-courier", $payload);
        $second->assertOk()->assertJson([
            'success' => true,
            'already_booked' => true,
            'tracking_number' => 'QX-ERP-001',
        ]);

        Http::assertSentCount(1);
    }

    public function test_rebook_after_cancel_creates_a_fresh_shipment_and_records_the_previous_tracking_number(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake([
            // Two bookings against the same reference_id — QuadX accepts repeat
            // bookings, so this returns a fresh tracking number the second time.
            'api.staging.quadx.xyz/v2/orders' => Http::sequence()
                ->push(['tracking_number' => 'QX-OLD-001', 'status' => 'for_pickup'], 200)
                ->push(['tracking_number' => 'QX-NEW-002', 'status' => 'for_pickup'], 200),
            'api.staging.quadx.xyz/v2/orders/*' => Http::response(['ok' => true], 200), // cancel
        ]);

        $order = $this->makeBookableOrder();
        $bookPayload = ['courier' => 'quadx', 'service' => 'next_day', 'parcel' => 'box', 'shipping_payment' => 'net_off'];

        $this->post($this->bookUrl($order), $bookPayload)->assertSessionHas('success');
        $this->assertSame('QX-OLD-001', $order->fresh()->courier_tracking_number);

        $this->post($this->cancelUrl($order))->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('canceled', $order->courier_status);
        // Cancel does not clear the tracking number — it's what makes the
        // "previous" value available to capture on the rebook below.
        $this->assertSame('QX-OLD-001', $order->courier_tracking_number);

        $this->post($this->bookUrl($order), $bookPayload)->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('QX-NEW-002', $order->courier_tracking_number);
        $this->assertSame('for_pickup', $order->courier_status);

        $meta = json_decode($order->courier_meta, true);
        $this->assertSame('QX-OLD-001', $meta['previous_tracking_number']);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'comment' => 'Rebooked with courier: QX-OLD-001 superseded by QX-NEW-002',
        ]);

        Http::assertSentCount(3); // book, cancel, rebook
    }

    // ------------------------------------------------------------------
    // Webhook ingest: valid token -> status map -> order + history + stock;
    // invalid token rejected via the hash_equals() path.
    // ------------------------------------------------------------------

    public function test_webhook_with_valid_token_applies_the_status_map_updates_history_and_adjusts_stock(): void
    {
        $this->installExtension();

        Setting::create(['group' => 'courier', 'key' => 'quadx_webhook_token', 'value' => 'super-secret-webhook-token']);

        $pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $delivered = OrderStatus::create(['store_id' => 1, 'name' => 'Delivered', 'sort_order' => 2, 'stock_action' => 'decrement']);

        Setting::create(['group' => 'courier', 'key' => 'quadx_status_map', 'value' => json_encode([
            'delivered' => ['order_status_id' => $delivered->id, 'notify' => false],
        ])]);

        $product = Product::create([
            'store_id' => 1,
            'name' => 'Guitar Strap',
            'slug' => 'guitar-strap',
            'price' => 500,
            'quantity' => 10,
            'track_stock' => true,
        ]);

        $order = $this->makeBookableOrder(['order_status_id' => $pending->id]);
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-WEBHOOK-001',
            'courier_status' => 'for_pickup',
        ])->save();

        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => 500,
            'quantity' => 2,
            'total' => 1000,
        ]);

        $response = $this->postJson('/webhooks/courier/quadx?token=super-secret-webhook-token', [
            'data' => [[
                'reference_id' => (string) $order->id,
                'tracking_number' => 'QX-WEBHOOK-001',
                'status' => 'delivered',
                'status_updated_at' => now()->toIso8601String(),
            ]],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $order->refresh();
        $this->assertSame('delivered', $order->courier_status);
        $this->assertSame($delivered->id, $order->order_status_id);
        $this->assertTrue((bool) $order->stock_adjusted);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_id' => $delivered->id,
            'comment' => 'Courier status: delivered',
        ]);

        $this->assertSame(8, $product->fresh()->quantity);
    }

    public function test_webhook_with_invalid_or_missing_token_is_rejected(): void
    {
        $this->installExtension();
        Setting::create(['group' => 'courier', 'key' => 'quadx_webhook_token', 'value' => 'the-real-token']);

        $order = $this->makeBookableOrder();
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-SECURE-001',
            'courier_status' => 'for_pickup',
        ])->save();

        $eventPayload = ['data' => [['reference_id' => (string) $order->id, 'status' => 'delivered']]];

        $this->postJson('/webhooks/courier/quadx?token=wrong-token', $eventPayload)
            ->assertStatus(403)
            ->assertJson(['error' => 'unauthorized']);

        $this->postJson('/webhooks/courier/quadx', $eventPayload) // no token query param at all
            ->assertStatus(403)
            ->assertJson(['error' => 'unauthorized']);

        // Neither rejected call touched the order.
        $this->assertSame('for_pickup', $order->fresh()->courier_status);
        $this->assertSame($order->order_status_id, $order->fresh()->order_status_id);
    }

    // ------------------------------------------------------------------
    // ERP book-courier endpoint: erp.auth pass/fail.
    // ------------------------------------------------------------------

    public function test_erp_book_courier_endpoint_rejects_missing_or_invalid_bearer_token(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->makeErpToken('the-real-erp-token');

        $order = $this->makeBookableOrder();
        Http::fake(); // any outbound courier call here would itself be a bug

        $this->postJson("/api/v1/orders/{$order->id}/book-courier", ['courier' => 'quadx'])
            ->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer totally-wrong-token')
            ->postJson("/api/v1/orders/{$order->id}/book-courier", ['courier' => 'quadx'])
            ->assertStatus(401);

        Http::assertNothingSent();
        $this->assertNull($order->fresh()->courier_tracking_number);
    }

    // ------------------------------------------------------------------
    // Poll command: faked responses, honors terminalStatuses().
    // ------------------------------------------------------------------

    public function test_poll_command_updates_non_terminal_orders_and_skips_terminal_ones(): void
    {
        $this->installExtension();
        // Registers QuadXFulfillment into CourierManager (normally deferred to
        // the next HTTP request via BootExtensions — see bookUrl()'s docblock —
        // but this test never makes one before calling artisan()).
        app(ExtensionManager::class)->bootEnabled();
        $this->registerPollCommand();
        $this->enableQuadxStaging();

        $pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);

        Setting::create(['group' => 'courier', 'key' => 'quadx_status_map', 'value' => json_encode([
            'in_transit' => ['order_status_id' => $shipped->id, 'notify' => false],
        ])]);

        $active = $this->makeBookableOrder(['order_status_id' => $pending->id]);
        $active->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-ACTIVE-001',
            'courier_status' => 'for_pickup', // not terminal -> must be polled
        ])->save();

        $terminal = $this->makeBookableOrder(['order_status_id' => $pending->id]);
        $terminal->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-DONE-002',
            'courier_status' => 'delivered', // terminal per QuadXFulfillment::terminalStatuses()
        ])->save();

        Http::fake([
            'api.staging.quadx.xyz/v2/orders/QX-ACTIVE-001' => Http::response([
                'status' => 'in_transit',
                'tracking_links' => ['delivery' => 'https://track.quadx.test/QX-ACTIVE-001'],
            ], 200),
        ]);

        $this->artisan('courier:poll-status')->assertExitCode(0);

        // Exactly one outbound getTracking() call — the terminal order's tracking
        // number never leaves the terminalStatuses() filter in the SQL query.
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'QX-ACTIVE-001'));

        $active->refresh();
        $this->assertSame('in_transit', $active->courier_status);
        $this->assertSame($shipped->id, $active->order_status_id); // StatusMapper::apply() fired
        $this->assertSame('https://track.quadx.test/QX-ACTIVE-001', $active->getRawOriginal('tracking_link'));
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $active->id,
            'comment' => 'Courier status: in_transit',
        ]);

        // Terminal order: completely untouched by this poll cycle.
        $terminal->refresh();
        $this->assertSame('delivered', $terminal->courier_status);
        $this->assertSame($pending->id, $terminal->order_status_id);
        $this->assertNull($terminal->getRawOriginal('tracking_link'));
    }

    public function test_poll_command_is_a_no_op_when_no_courier_is_enabled(): void
    {
        // No enableQuadxStaging() call — CourierManager::enabled() is empty,
        // so the command must exit cleanly without touching any order or
        // making a single outbound request.
        $this->installExtension();
        // Registers QuadXFulfillment into CourierManager (normally deferred to
        // the next HTTP request via BootExtensions — see bookUrl()'s docblock —
        // but this test never makes one before calling artisan()).
        app(ExtensionManager::class)->bootEnabled();
        $this->registerPollCommand();

        $order = $this->makeBookableOrder();
        $order->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => 'QX-ORPHAN-001',
            'courier_status' => 'for_pickup',
        ])->save();

        Http::fake();

        $this->artisan('courier:poll-status')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame('for_pickup', $order->fresh()->courier_status);
    }
}
