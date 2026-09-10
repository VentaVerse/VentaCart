<?php

namespace Tests\Feature\AdminV2;

use App\Models\Country;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use Extensions\CourierFulfillment\PlacementMap;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxClient;
use Extensions\CourierFulfillment\Jobs\ProcessCourierWebhookJob;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\StatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        // One home: the hub itself sits at /settings; couriers and the
        // other literals are root-level siblings.
        return '/' . $this->prefix . '/extensions/courier-fulfillment' . ($suffix === '' ? '/settings' : $suffix);
    }

    private function queueUrl(): string
    {
        return '/' . $this->prefix . '/extensions/courier-fulfillment';
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
        // Registers the extension's runtime PSR-4 autoloader up front, so
        // this file can reference Extensions\CourierFulfillment\* (the
        // module-owned placement mapping) before its first HTTP request.
        app(ExtensionManager::class)->bootEnabled();
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

    // ------------------------------------------------------------------
    // Task T3 helpers — SPX app-level webhook credentials (app-id/secret
    // only; the webhook signature check never touches user_id/user_secret)
    // plus a header builder that signs a given body EXACTLY the way
    // SpxClient::sign() (and, in production, real SPX) would.
    // ------------------------------------------------------------------

    private const SPX_TEST_APP_ID = 424242;
    private const SPX_TEST_APP_SECRET = 'fake-app-secret-not-real';

    private function enableSpxWebhookCredentials(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'spx_mode', 'value' => 'uat']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => (string) self::SPX_TEST_APP_ID]);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString(self::SPX_TEST_APP_SECRET)]);
    }

    /**
     * Signs $body exactly as SpxClient::post() signs an outbound request
     * (integration-brief.md §2) — since SPX's webhook uses the SAME
     * algorithm to sign ITS request to us, this doubles as "what SPX itself
     * would send". $rawBody must be the SAME string postJson() will encode
     * (json_encode($body, 0)) so the signature isn't computed over bytes
     * different from what actually hits the wire — see SpxClient::post()'s
     * own docblock for why that byte-fidelity matters.
     *
     * @return array{headers:array<string,string>, appId:int, timestamp:int, randomNum:int}
     */
    private function spxSignedHeaders(array $body, ?int $appId = null, ?string $appSecret = null): array
    {
        $appId = $appId ?? self::SPX_TEST_APP_ID;
        $appSecret = $appSecret ?? self::SPX_TEST_APP_SECRET;
        $timestamp = time();
        $randomNum = 918273645;
        $payload = json_encode($body);

        $sign = SpxClient::sign($appId, $timestamp, $randomNum, $payload, $appSecret);

        return [
            'headers' => [
                'app-id'     => (string) $appId,
                'check-sign' => $sign,
                'timestamp'  => (string) $timestamp,
                'random-num' => (string) $randomNum,
            ],
            'appId'     => $appId,
            'timestamp' => $timestamp,
            'randomNum' => $randomNum,
        ];
    }

    /** Cebu (non-NCR) address deliberately, so QuadXLocationResolver's NCR "City" suffix rewriting never kicks in and payload assertions can compare raw strings. */
    private function makeBookableOrder(array $overrides = []): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        // Placement is module data with NO default (owner ruling,
        // 2026-08-09), so a bookable fixture places its own status.
        PlacementMap::put($status->id, 'to_pack');
        \Tests\Support\FulfillmentSettings::selectFor('to_pack', (int) $status->id);
        // fulfillment-placement Task 3: a store must have somewhere to put a
        // booked order (a To-Handover-placed status), or FulfillmentService::
        // book() refuses before the outbound call rather than leaving a
        // booked order actionless in To Pack. OrderStatusSeeder seeds this
        // status on a real install; these tests build their statuses by hand,
        // so the fixture carries it too.
        $handover = OrderStatus::firstOrCreate(
            ['store_id' => 1, 'name' => 'Order Being Shipped'],
            ['sort_order' => 4],
        );
        PlacementMap::put($handover->id, 'to_handover');
        \Tests\Support\FulfillmentSettings::selectFor('to_handover', (int) $handover->id);

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

    /** An API key permitted everything, returning the secret for the Bearer header. */
    private function makeErpToken(string $token = 'erp-test-token-abc123'): string
    {
        $this->apiKey($token);

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
    // Save round-trip through the derived allowlist. Task W3 (spx-courier
    // plan) split the combined settings page into a hub + per-courier
    // pages — a sanctioned relocation, so these tests now post each
    // courier's keys to ITS page and module keys to the hub. Stored keys
    // and values are unchanged (FulfillmentSettingsTest proves the split's
    // own guarantees).
    // ------------------------------------------------------------------

    public function test_settings_save_round_trips_through_the_derived_allowlist(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl('/quadx'), [
            'settings' => [
                'quadx_enabled' => '1',
                'quadx_mode' => 'production',
                'quadx_default_service' => 'same_day_pickup',
                'quadx_default_parcel' => 'box',
            ],
        ])->assertRedirect();

        $this->post($this->settingsUrl(), [
            'settings' => ['fulfillment_enabled' => '1'],
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'production']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_default_service', 'value' => 'same_day_pickup']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'quadx_default_parcel', 'value' => 'box']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'fulfillment_enabled', 'value' => '1']);
    }

    public function test_unknown_keys_are_discarded(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl('/quadx'), [
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

        $this->post($this->settingsUrl('/quadx'), [
            'settings' => ['quadx_staging_api_key' => 'super-secret-key-1234'],
        ])->assertRedirect();

        $stored = Setting::where('group', 'courier')->where('key', 'quadx_staging_api_key')->value('value');
        $this->assertNotSame('super-secret-key-1234', $stored);
        $this->assertSame('super-secret-key-1234', Crypt::decryptString($stored));

        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            ->assertSee('1234') // last 4 of the mask
            ->assertDontSee('super-secret-key-1234');
    }

    public function test_blank_submission_keeps_the_stored_encrypted_value(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl('/quadx'), [
            'settings' => ['quadx_staging_api_key' => 'original-secret'],
        ])->assertRedirect();
        $original = Setting::where('group', 'courier')->where('key', 'quadx_staging_api_key')->value('value');

        // Re-save with the field left blank (nothing typed to change it).
        $this->post($this->settingsUrl('/quadx'), [
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

        // Hub: one card per registered courier (W3 split); the schema fields
        // themselves render on the courier's own page.
        $this->get($this->settingsUrl())->assertOk()->assertSee('Fake Courier');

        $response = $this->get($this->settingsUrl('/fake'));
        $response->assertOk()
            ->assertSee('Fake Courier')                                  // page title, from getName()
            ->assertSee('name="settings[fake_enabled]"', false)
            ->assertSee('name="settings[fake_mode]"', false)
            ->assertSee('name="settings[fake_staging_token]"', false)
            ->assertSee('name="settings[fake_production_token]"', false)
            ->assertSee('name="settings[fake_note]"', false);

        // Each courier's keys posted through its OWN page, proving neither
        // allowlist/encryption pass touches the other courier's data.
        $this->post($this->settingsUrl('/quadx'), [
            'settings' => [
                'quadx_enabled' => '1',
                'quadx_staging_api_key' => 'quadx-secret',
            ],
        ])->assertRedirect();

        $this->post($this->settingsUrl('/fake'), [
            'settings' => [
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
            ->assertSee('To Pack'); // the real queue's filter tabs render
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
            ->assertSee('Go to Fulfillment settings')
            ->assertDontSee('To Pack'); // the real queue's filter tabs are absent
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
    // Owner request (fix round): the module toggle also owns the MENU.
    // Switching fulfillment off takes its entry out of the sidebar and the
    // command palette, while the queue URL keeps answering with the paused
    // page - hiding a menu entry is not a permission check, and a saved
    // link has to explain itself rather than 404.
    // ------------------------------------------------------------------

    /**
     * Nav item keys the registry currently offers.
     *
     * bootEnabled() first: the Fulfillment entry points at an
     * extension-provided route name, which only exists once the extension's
     * provider has booted. An HTTP request does that for itself, but a
     * registry read straight from the test process needs it done explicitly
     * (the same reason the other fulfillment suites boot it up front).
     */
    private function navItemKeys(): array
    {
        app(ExtensionManager::class)->bootEnabled();

        return collect(\App\Support\AdminNav::groups(null))
            ->flatMap(fn (array $group) => collect($group['items'])->pluck('key'))
            ->all();
    }

    public function test_fulfillment_nav_entry_renders_while_the_module_setting_is_unset_or_on(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // Unset means on: a merchant who never touched the toggle must see
        // the module they just installed.
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'fulfillment_enabled']);
        $this->assertContains('fulfillment', $this->navItemKeys());

        Setting::updateOrCreate(['group' => 'courier', 'key' => 'fulfillment_enabled'], ['value' => '1']);
        $this->assertContains('fulfillment', $this->navItemKeys());

        // And it is really in the rendered sidebar, not just the registry.
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($this->queueUrl(), false);
    }

    public function test_fulfillment_nav_entry_disappears_when_the_module_is_switched_off(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'fulfillment_enabled'], ['value' => '0']);

        $this->assertNotContains('fulfillment', $this->navItemKeys());
        // The command palette reads the same registry, so it goes too.
        $this->assertNotContains(
            'Fulfillment',
            collect(\App\Support\AdminNav::paletteEntries(null))->pluck('label')->all()
        );

        // Every OTHER Sales entry is untouched: this hides one item, never
        // its group.
        $this->assertContains('orders', $this->navItemKeys());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee($this->queueUrl(), false);
    }

    public function test_the_queue_url_still_answers_with_the_paused_page_while_the_nav_entry_is_hidden(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'fulfillment_enabled'], ['value' => '0']);

        $this->assertNotContains('fulfillment', $this->navItemKeys());

        // A bookmarked URL explains its own state: 200 and the paused page,
        // never a 404 and never a redirect.
        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('Fulfillment is paused')
            ->assertSee('hidden from the main menu');
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
        PlacementMap::put($pending->id, 'to_pack');
        \Tests\Support\FulfillmentSettings::selectFor('to_pack', (int) $pending->id);
        PlacementMap::put($delivered->id, 'delivered');
        \Tests\Support\FulfillmentSettings::selectFor('delivered', (int) $delivered->id);

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
        PlacementMap::put($status->id, 'to_pack');
        \Tests\Support\FulfillmentSettings::selectFor('to_pack', (int) $status->id);
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
        //
        // fulfillment-placement Task 2: the status is placed under To
        // Handover, the tab whose action set includes Cancel booking and
        // the drawer opener this test reads its payload from. The subject
        // is unchanged (each row resolves its OWN courier), only the tab
        // those actions belong to is now named by the order's status.
        $status = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Order Being Shipped'], ['sort_order' => 1, 'is_default' => true]);
        PlacementMap::put($status->id, 'to_handover');
        \Tests\Support\FulfillmentSettings::selectFor('to_handover', (int) $status->id);

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

        // Webhook URL renders on the courier's OWN page (owner ruling
        // 2026-08-19), generically, not just for QuadX; the hub's menu
        // simply lists the courier.
        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee($this->settingsUrl('/fake'), false)
            ->assertDontSee('/webhooks/courier/fake?token=', false);

        $response = $this->get($this->settingsUrl('/fake'));
        $response->assertSee('/webhooks/courier/fake?token=', false);
        $response->assertOk()
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

    public function test_booking_sends_the_courier_message_as_quadx_remarks_and_never_the_store_message(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        $this->enableQuadxStaging();

        Http::fake([
            'api.staging.quadx.xyz/v2/orders' => Http::response([
                'id' => 556,
                'tracking_number' => 'QX-MSG-001',
                'status' => 'for_pickup',
                'reference_id' => '1',
            ], 200),
        ]);

        $order = $this->makeBookableOrder([
            'comment' => 'Please pack carefully',
            'courier_comment' => 'Deliver between Aug 20 and Aug 22',
        ]);

        $this->post($this->bookUrl($order), [
            'courier' => 'quadx',
            'service' => 'next_day',
            'parcel' => 'box',
            'shipping_payment' => 'net_off',
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.staging.quadx.xyz/v2/orders') {
                return false;
            }
            $body = $request->data();

            // Only the buyer's message to the courier rides the remarks
            // field; the message to the store stays in the store.
            return ($body['delivery_address']['remarks'] ?? null) === 'Deliver between Aug 20 and Aug 22';
        });
    }

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
                // QuadX requires `total` on every booking, prepaid included:
                // omitting it for non-COD (d781db9d) made the live API answer
                // 422 "The total field is required" and no prepaid order could
                // be booked. Restored in 33adec6f.
                && (float) $body['total'] === 1100.0
                && array_key_exists('amount', $body['items'][0])
                && $body['items'][0]['description'] !== ''
                && (int) $body['items'][0]['quantity'] >= 1
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

    /**
     * Owner directive 2026-08-19: a disabled courier is disabled for the
     * ERP too — the API must refuse to book on it. The gate is
     * FulfillmentService::book()'s isEnabled() check (shared with the
     * admin queue); this pins it from the ERP side so it can never
     * regress into an ERP-only bypass. Covers every booking-side
     * endpoint: book, estimate, pickup-slots.
     */
    public function test_erp_cannot_book_estimate_or_fetch_slots_on_a_disabled_courier(): void
    {
        $this->installExtension();
        $token = $this->makeErpToken();

        // QuadX fully credentialed but its enable toggle OFF — the exact
        // state the admin's courier settings switch produces.
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '0']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_mode', 'value' => 'staging']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('test-api-key')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('test-secret-key')]);

        Http::fake(); // any outbound call to the courier is a failure

        $order = $this->makeBookableOrder();
        $auth = fn () => $this->withHeader('Authorization', "Bearer {$token}");

        $auth()->postJson("/api/v1/orders/{$order->id}/book-courier", ['courier' => 'quadx'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'courier_unavailable');

        $auth()->postJson("/api/v1/orders/{$order->id}/estimate", ['courier' => 'quadx'])
            ->assertStatus(409);

        $auth()->getJson("/api/v1/orders/{$order->id}/pickup-slots?courier=quadx")
            ->assertStatus(409);

        // Omitting the courier cannot sidestep the gate either: with no
        // enabled courier, the default resolves to none.
        $auth()->postJson("/api/v1/orders/{$order->id}/book-courier", [])
            ->assertStatus(409)
            ->assertJsonPath('code', 'courier_unavailable');

        // Nothing left the building and the order carries no booking.
        Http::assertNothingSent();
        $order->refresh();
        $this->assertNull($order->courier_tracking_number);
        $this->assertNull($order->courier_provider);

        // Flag ON but unconfigured (credentials removed) is equally
        // refused — isEnabled()'s other half.
        Setting::where('group', 'courier')->where('key', 'quadx_enabled')->update(['value' => '1']);
        Setting::where('group', 'courier')->where('key', 'quadx_staging_api_key')->delete();
        Setting::where('group', 'courier')->where('key', 'quadx_staging_secret_key')->delete();

        $auth()->postJson("/api/v1/orders/{$order->id}/book-courier", ['courier' => 'quadx'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'courier_unavailable');
        Http::assertNothingSent();
    }

    /**
     * Owner ruling 2026-08-19: hovering a picking row's photo holds it up
     * at a size that can be checked against the parcel in hand. The panel
     * is position:fixed because the table wrapper clips both axes.
     */
    public function test_picking_row_photos_carry_the_hover_preview(): void
    {
        $this->installExtension();
        $this->actingAs(User::factory()->create());

        $order = $this->makeBookableOrder();
        $product = Product::create(['store_id' => 1, 'name' => 'Patch Cable', 'slug' => 'patch-cable', 'price' => 500]);

        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'name' => 'Patch Cable', 'sku' => 'QB-SL-10',
            'image' => 'catalog/demo/cable.jpg',
            'price' => 500, 'quantity' => 1, 'total' => 500,
        ]);

        $html = $this->get($this->queueUrl())->assertOk()->getContent();

        // The photo asks to be hovered, and both ends of the interaction
        // are wired.
        $this->assertStringContainsString('v-zoomable', $html);
        $this->assertStringContainsString('showZoom(', $html);
        $this->assertStringContainsString('hideZoom()', $html);
        // The panel it opens into exists once, outside the clipped table.
        $this->assertSame(1, substr_count($html, 'class="v-zoom"'));
    }

    /**
     * Owner ruling 2026-08-19: the picking row names both facts behind
     * their own keys, identifier first — "SKU: X | Variant: Y" — with the
     * rule between them present only when there are two facts to separate.
     */
    public function test_the_picking_row_labels_the_sku_and_the_variation(): void
    {
        $this->installExtension();
        $this->actingAs(User::factory()->create());

        $order = $this->makeBookableOrder();
        $product = Product::create(['store_id' => 1, 'name' => 'Patch Cable', 'slug' => 'patch-cable', 'price' => 500]);

        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'name' => 'Patch Cable', 'sku' => 'QB-SL-10', 'variant_label' => '10 Ft',
            'price' => 500, 'quantity' => 1, 'total' => 500,
        ]);
        // A line with no variation must not render a dangling separator.
        OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'name' => 'Plain Pedal', 'sku' => 'VALETON-GP5',
            'price' => 500, 'quantity' => 1, 'total' => 500,
        ]);

        $html = $this->get($this->queueUrl())->assertOk()->getContent();

        $this->assertStringContainsString('SKU:', $html);
        $this->assertStringContainsString('Variant:', $html);
        $this->assertStringContainsString('QB-SL-10', $html);
        // The variation keeps its chip; the key sits beside it, not inside.
        $this->assertStringContainsString('v-variant', $html);

        // One separator for the one line that carries both facts.
        $this->assertSame(1, substr_count($html, 'v-metasep'));
    }

    /**
     * Owner ruling 2026-08-19: step 2 always names where the parcel leaves
     * from, for every courier, even when the store keeps a single pickup
     * address (it used to render only with two or more, so most stores
     * never saw it). The option carries the address NAME; its street lines
     * confirm underneath, driven by the pickupSummary getter.
     */
    public function test_the_booking_form_always_offers_the_pickup_address_by_name(): void
    {
        $this->installExtension();
        $this->enableQuadxStaging();
        $this->actingAs(User::factory()->create());

        $country = Country::firstOrCreate(
            ['iso2' => 'PH'],
            ['iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]
        );
        // ONE address on file: the case that used to render no picker.
        PickupAddress::create([
            'label' => 'Main warehouse',
            'contact_name' => 'Warehouse Team',
            'contact_phone' => '09171112222',
            'address_1' => '456 Warehouse Ave',
            'barangay' => 'Bel-Air',
            'city' => 'Makati',
            'state' => 'Metro Manila',
            'postcode' => '1200',
            'country_id' => $country->id,
            'is_default' => true,
        ]);

        $this->makeBookableOrder();

        $this->get($this->queueUrl())
            ->assertOk()
            ->assertSee('pickup_address_id', false)
            ->assertSee('Ships from')
            // The option is the name alone, not name-plus-address.
            ->assertSee('>Main warehouse</option>', false)
            // The street lines travel to Alpine for the line beneath.
            ->assertSee('pickupSummary', false)
            ->assertSee('456 Warehouse Ave', false);
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

    /**
     * fulfillment-tabs-v Task 4 UPDATE (new cancel semantics, not a
     * weakening): the outbound QuadX calls are byte-identical (book, cancel,
     * rebook — Http::assertSentCount(3) still holds), but an ADMIN booking
     * cancel now RESETS the courier fields once QuadX confirms, snapshotting
     * the old booking into courier_meta.booking_history and returning the
     * order to To Ship > To Pack. The rebook is therefore a FRESH booking
     * (no previous_tracking_number, no "Rebooked with courier" history line)
     * that carries the audit snapshot forward.
     */
    public function test_rebook_after_cancel_resets_the_booking_and_books_fresh_with_a_history_snapshot(): void
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

        // The reset: live courier fields cleared, the order reads as never
        // booked (back in To Pack), with the old booking snapshotted.
        $this->assertNull($order->courier_provider);
        $this->assertNull($order->courier_status);
        $this->assertNull($order->courier_tracking_number);
        $this->assertSame('to_pack', \Extensions\CourierFulfillment\FulfillmentService::tabBucket($order));

        $meta = json_decode($order->courier_meta, true);
        $this->assertCount(1, $meta['booking_history']);
        $this->assertSame('quadx', $meta['booking_history'][0]['courier']);
        $this->assertSame('QX-OLD-001', $meta['booking_history'][0]['tracking_number']);
        $this->assertTrue($meta['booking_history'][0]['admin_cancelled']);
        $this->assertNotEmpty($meta['booking_history'][0]['cancelled_at']);

        // fulfillment-placement Task 3: "returned to To Pack" is no longer a
        // claim about the courier fields alone - the cancel now actually
        // moves the CORE STATUS back (here to 'Pending', the To-Pack-placed
        // status this order held before booking), and the history line names
        // the status it landed on.
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'comment' => 'Courier booking cancelled: QX-OLD-001 (QuadX). Order status set to Pending, back under To Ship, To Pack.',
        ]);

        $this->post($this->bookUrl($order), $bookPayload)->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('QX-NEW-002', $order->courier_tracking_number);
        $this->assertSame('for_pickup', $order->courier_status);

        $meta = json_decode($order->courier_meta, true);
        // Fresh booking, not a rebook-in-place — and the snapshot survives.
        $this->assertNull($meta['previous_tracking_number']);
        $this->assertSame('QX-OLD-001', $meta['booking_history'][0]['tracking_number']);
        $this->assertDatabaseMissing('order_histories', [
            'order_id' => $order->id,
            'comment' => 'Rebooked with courier: QX-OLD-001 superseded by QX-NEW-002',
        ]);

        Http::assertSentCount(3); // book, cancel, rebook — unchanged contract
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
        $delivered = OrderStatus::create(['store_id' => 1, 'name' => 'Delivered', 'sort_order' => 2, 'deducts_stock' => true]);

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
            'comment' => 'QuadX: Delivered',
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
    // Task T3: SPX's signed, fast-ack webhook branch. No `?token=` query
    // param — auth is the `check-sign` header (see spxSignedHeaders()).
    // ------------------------------------------------------------------

    public function test_spx_webhook_with_valid_signature_is_processed_end_to_end(): void
    {
        $this->installExtension();
        $this->enableSpxWebhookCredentials();

        $pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1, 'is_default' => true]);
        $delivered = OrderStatus::create(['store_id' => 1, 'name' => 'Delivered', 'sort_order' => 2]);

        Setting::create(['group' => 'courier', 'key' => 'spx_status_map', 'value' => json_encode([
            '4001' => ['order_status_id' => $delivered->id, 'notify' => false],
        ])]);

        $order = $this->makeBookableOrder(['order_status_id' => $pending->id]);
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000001',
            'courier_status' => '2001',
        ])->save();

        $body = [
            'status' => 'Delivered',
            'status_code' => '4001',
            'message' => 'Parcel delivered',
            'tracking_no' => 'SPEPH000000000001',
            'order_id' => (string) $order->id,
            'timestamp' => now()->getTimestamp(),
            'id' => 'evt-001',
        ];
        $signed = $this->spxSignedHeaders($body);

        // No ->afterResponse() fake here: MakesHttpRequests::call() DOES
        // invoke $kernel->terminate() (see that method), so the queued job
        // has already run by the time postJson() returns — genuinely
        // end-to-end, not just "the controller said ok".
        $response = $this->postJson('/webhooks/courier/spx', $body, $signed['headers']);

        $response->assertOk()->assertJson(['ok' => true]);

        $order->refresh();
        $this->assertSame('4001', $order->courier_status);
        $this->assertSame($delivered->id, $order->order_status_id);
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'order_status_id' => $delivered->id,
            'comment' => 'SPX: Delivered',
        ]);
    }

    public function test_spx_webhook_dispatches_the_ingest_job_after_the_response_rather_than_inline(): void
    {
        $this->installExtension();
        $this->enableSpxWebhookCredentials();
        Bus::fake();

        $order = $this->makeBookableOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000099',
            'courier_status' => '2001',
        ])->save();

        $body = [
            'status' => 'Delivered', 'status_code' => '4001', 'tracking_no' => 'SPEPH000000000099',
            'order_id' => (string) $order->id, 'timestamp' => now()->getTimestamp(), 'id' => 'evt-002',
        ];
        $signed = $this->spxSignedHeaders($body);

        $response = $this->postJson('/webhooks/courier/spx', $body, $signed['headers']);
        $response->assertOk()->assertJson(['ok' => true]);

        // The mechanism this task's brief requires: dispatched via
        // ->afterResponse(), not a plain synchronous dispatch — and with
        // Bus faked, it genuinely never ran, so the order is untouched even
        // though the HTTP response already came back "ok".
        Bus::assertDispatchedAfterResponse(
            ProcessCourierWebhookJob::class,
            fn (ProcessCourierWebhookJob $job) => $job->provider === 'spx'
                && ($job->events[0]['tracking_number'] ?? null) === 'SPEPH000000000099'
                && ($job->events[0]['status'] ?? null) === '4001'
        );
        $this->assertSame('2001', $order->fresh()->courier_status);
    }

    public function test_spx_webhook_with_invalid_signature_is_rejected_and_writes_nothing(): void
    {
        $this->installExtension();
        $this->enableSpxWebhookCredentials();

        $order = $this->makeBookableOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000002',
            'courier_status' => '2001',
        ])->save();

        $body = [
            'status' => 'Delivered', 'status_code' => '4001', 'tracking_no' => 'SPEPH000000000002',
            'order_id' => (string) $order->id, 'timestamp' => now()->getTimestamp(), 'id' => 'evt-003',
        ];

        // Tampered check-sign (right shape, wrong value).
        $signed = $this->spxSignedHeaders($body);
        $tamperedHeaders = $signed['headers'];
        $tamperedHeaders['check-sign'] = str_repeat('0', 64);

        $this->postJson('/webhooks/courier/spx', $body, $tamperedHeaders)
            ->assertStatus(401)
            ->assertJson(['error' => 'unauthorized']);

        // Missing signature headers entirely.
        $this->postJson('/webhooks/courier/spx', $body)
            ->assertStatus(401)
            ->assertJson(['error' => 'unauthorized']);

        // Signed with the WRONG app-secret (a stolen/guessed body signed by
        // someone who doesn't actually hold our app-secret).
        $wrongSecret = $this->spxSignedHeaders($body, appSecret: 'not-the-real-secret');
        $this->postJson('/webhooks/courier/spx', $body, $wrongSecret['headers'])
            ->assertStatus(401)
            ->assertJson(['error' => 'unauthorized']);

        $order->refresh();
        $this->assertSame('2001', $order->courier_status);
        $this->assertDatabaseMissing('order_histories', ['order_id' => $order->id]);
    }

    // ------------------------------------------------------------------
    // Task T3 (F2 fix): tracking-number-fallback webhook matching is scoped
    // to courier_provider — a tracking-number collision between two
    // registered couriers can no longer cross-apply. Proven both directions
    // (QuadX event never touches the SPX order sharing its tracking number,
    // and vice versa).
    // ------------------------------------------------------------------

    public function test_webhook_tracking_number_fallback_never_crosses_courier_providers(): void
    {
        $this->installExtension();
        Setting::create(['group' => 'courier', 'key' => 'quadx_webhook_token', 'value' => 'quadx-secret-token']);
        $this->enableSpxWebhookCredentials();

        $delivered = OrderStatus::create(['store_id' => 1, 'name' => 'Delivered', 'sort_order' => 2]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_status_map', 'value' => json_encode([
            'delivered' => ['order_status_id' => $delivered->id, 'notify' => false],
        ])]);
        Setting::create(['group' => 'courier', 'key' => 'spx_status_map', 'value' => json_encode([
            '4001' => ['order_status_id' => $delivered->id, 'notify' => false],
        ])]);

        $sharedTrackingNumber = 'COLLIDE-000001';

        $quadxOrder = $this->makeBookableOrder();
        $quadxOrder->forceFill([
            'courier_provider' => 'quadx',
            'courier_tracking_number' => $sharedTrackingNumber,
            'courier_status' => 'for_pickup',
        ])->save();

        $spxOrder = $this->makeBookableOrder();
        $spxOrder->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => $sharedTrackingNumber,
            'courier_status' => '1001',
        ])->save();

        // Direction 1: a QuadX webhook event carrying only the (colliding)
        // tracking number — no reference_id — must resolve to the QuadX
        // order only.
        $this->postJson('/webhooks/courier/quadx?token=quadx-secret-token', [
            'data' => [[
                'tracking_number' => $sharedTrackingNumber,
                'status' => 'delivered',
            ]],
        ])->assertOk();

        $this->assertSame('delivered', $quadxOrder->fresh()->courier_status);
        $this->assertSame($delivered->id, $quadxOrder->fresh()->order_status_id);
        // The SPX order sharing the same tracking number is untouched.
        $this->assertSame('1001', $spxOrder->fresh()->courier_status);
        $this->assertNotSame($delivered->id, $spxOrder->fresh()->order_status_id);

        // Direction 2: an SPX webhook event carrying only the (colliding)
        // tracking number — must resolve to the SPX order only.
        $body = ['status' => 'Delivered', 'status_code' => '4001', 'tracking_no' => $sharedTrackingNumber, 'timestamp' => now()->getTimestamp()];
        $signed = $this->spxSignedHeaders($body);
        $this->postJson('/webhooks/courier/spx', $body, $signed['headers'])->assertOk();

        $this->assertSame('4001', $spxOrder->fresh()->courier_status);
        $this->assertSame($delivered->id, $spxOrder->fresh()->order_status_id);
        // The QuadX order (already 'delivered' from direction 1) is
        // untouched by this second event — still exactly what direction 1
        // left it as, not re-applied or altered by the SPX event.
        $this->assertSame('delivered', $quadxOrder->fresh()->courier_status);
    }

    // ------------------------------------------------------------------
    // Task T3: the label-file serving route requires authentication —
    // guests never reach a cached label PDF directly.
    // ------------------------------------------------------------------

    public function test_label_file_route_requires_authentication(): void
    {
        $this->installExtension();

        $order = $this->makeBookableOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000003',
        ])->save();

        // Not actingAs() anyone — a genuine guest request.
        $response = $this->get($this->queueUrl() . '/' . $order->id . '/label-file');

        $response->assertRedirect();
    }

    public function test_label_file_route_404s_when_no_label_has_been_cached_yet(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $order = $this->makeBookableOrder();
        $order->forceFill([
            'courier_provider' => 'spx',
            'courier_tracking_number' => 'SPEPH000000000004',
        ])->save();

        Storage::fake('local');

        $this->get($this->queueUrl() . '/' . $order->id . '/label-file')
            ->assertStatus(404);
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
        // Owner ruling 2026-09-03: the GoGo Xpress tracker only. The poll's
        // response carries a tracking_links.delivery above, and it is
        // deliberately ignored in favour of the public tracker for the mode
        // this parcel was booked in.
        $this->assertSame('https://app.staging.gogoxpress.com/track/QX-ACTIVE-001', $active->getRawOriginal('tracking_link'));
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $active->id,
            'comment' => 'QuadX: In transit',
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
