<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\StatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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

    private function installExtension(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
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
}
