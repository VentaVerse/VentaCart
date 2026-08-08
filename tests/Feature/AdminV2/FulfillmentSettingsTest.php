<?php

namespace Tests\Feature\AdminV2;

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
 * Task W3 of the Fulfillment Workflow v2 + SPX plan: the single combined
 * courier settings page split into a HUB (module-level settings + one card
 * per registered courier) and PER-COURIER PAGES at
 * settings/modules/courier-fulfillment/{courier}. See
 * docs/superpowers/plans/2026-08-08-spx-courier.md (Task W3) and the
 * workflow spec's Part 4.
 *
 * The load-bearing guarantee here is allowlist SCOPING: a courier page's
 * save may only ever write keys derived from THAT courier's own
 * settingsSchema() (plus its "{key}_status_map"), and the hub's save may
 * only write the module keys — proven with FakeCourierFulfillment so the
 * isolation is generic, not QuadX-shaped. Settings keys and stored values
 * are FROZEN by the plan: this is a page split, not a data change.
 */
class FulfillmentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');
    }

    /**
     * CourierManager's registry is process-global static state — any courier
     * a test registers must be un-registered after, or it leaks into every
     * later test in this run (same convention as FulfillmentTest::tearDown()).
     * Guarded with class_exists(no-autoload): if no test in this process has
     * loaded the extension's classes yet (its PSR-4 autoloader only registers
     * on bootEnabled()), there is nothing to clean up — and referencing the
     * class here would itself fatal.
     */
    protected function tearDown(): void
    {
        if (class_exists(CourierManager::class, false)) {
            $ref = new \ReflectionProperty(CourierManager::class, 'registered');
            $ref->setAccessible(true);
            $registered = $ref->getValue();
            unset($registered['fake']);
            $ref->setValue(null, $registered);
        }

        parent::tearDown();
    }

    /**
     * Raw URL strings, not route() — the extension's routes are registered
     * dynamically by BootExtensions on the FIRST request after
     * installExtension(), so route() would throw before any request has gone
     * through the HTTP kernel (same convention as FulfillmentTest).
     */
    private function settingsUrl(string $suffix = ''): string
    {
        return '/' . $this->prefix . '/settings/modules/courier-fulfillment' . $suffix;
    }

    private function installExtension(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install('courier-fulfillment');
        // Register the extension's PSR-4 autoloader + provider NOW, so tests
        // may reference CourierManager before their first HTTP request (the
        // BootExtensions middleware would otherwise do this lazily on that
        // request). Idempotent — the poll-command tests in FulfillmentTest
        // use the same call for the same reason.
        $manager->bootEnabled();
    }

    // ------------------------------------------------------------------
    // Hub: module fields + one card per registered courier, and nothing
    // that belongs to a courier's own page.
    // ------------------------------------------------------------------

    public function test_hub_shows_module_fields_and_one_card_per_registered_courier(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        $this->get($this->settingsUrl())
            ->assertOk()
            // Module-level fields stay on the hub.
            ->assertSee('name="settings[fulfillment_enabled]"', false)
            ->assertSee('name="settings[cod_payment_methods][]"', false)
            // One card per registered courier, linking to its own page.
            ->assertSee('QuadX')
            ->assertSee('Fake Courier')
            ->assertSee($this->settingsUrl('/quadx'), false)
            ->assertSee($this->settingsUrl('/fake'), false)
            // Courier schema fields moved OFF the hub onto the courier pages.
            ->assertDontSee('name="settings[quadx_mode]"', false)
            ->assertDontSee('name="settings[fake_mode]"', false)
            // Status mapping moved onto the courier pages too.
            ->assertDontSee('quadx_status_map', false);
    }

    public function test_hub_courier_card_shows_enabled_state_mode_chip_and_credential_hint(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // Unconfigured: disabled, staging mode (schema default), no creds.
        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('Disabled')
            ->assertSee('Staging')
            ->assertSee('2 of 2 credentials missing');

        // Enabled + both staging credentials stored -> complete.
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('k')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('s')]);

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('Enabled')
            ->assertSee('Credentials set');
    }

    public function test_webhook_urls_stay_on_the_hub_not_the_courier_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('/webhooks/courier/quadx?token=', false);

        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            ->assertDontSee('/webhooks/courier/quadx?token=', false);
    }

    // ------------------------------------------------------------------
    // Hub save: module keys ONLY — a courier key posted at the hub is
    // discarded, never persisted.
    // ------------------------------------------------------------------

    public function test_hub_save_persists_module_keys_only_never_courier_keys(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        $this->post($this->settingsUrl(), [
            'settings' => [
                'fulfillment_enabled' => '0',
                'cod_payment_methods' => ['__none__', 'cod'],
                // Courier + junk keys smuggled into the hub form.
                'quadx_enabled' => '1',
                'fake_note' => 'sneaky',
                'not_a_real_setting' => 'nope',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'fulfillment_enabled', 'value' => '0']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'cod_payment_methods']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_enabled']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'fake_note']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'not_a_real_setting']);
    }

    // ------------------------------------------------------------------
    // Per-courier page: renders that courier's schema + its actions, and
    // none of the module-level fields.
    // ------------------------------------------------------------------

    public function test_courier_page_renders_its_schema_fields_and_actions(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            ->assertSee('name="settings[quadx_enabled]"', false)
            ->assertSee('name="settings[quadx_mode]"', false)
            ->assertSee('name="settings[quadx_default_service]"', false)
            // Its actions live here now.
            ->assertSee('Test Connection')
            ->assertSee($this->settingsUrl('/quadx/test-connection'), false)
            ->assertSee($this->settingsUrl('/quadx/refresh-statuses'), false)
            // Status-mapping UI moved onto this page.
            ->assertSee('name="settings[quadx_status_map][delivered][order_status_id]"', false)
            // Module fields belong to the hub, not here.
            ->assertDontSee('name="settings[fulfillment_enabled]"', false)
            ->assertDontSee('cod_payment_methods', false);
    }

    // ------------------------------------------------------------------
    // THE W3 GUARANTEE: saving one courier's page can never touch another
    // courier's keys, nor the module keys — the allowlist is derived from
    // THAT courier's schema only.
    // ------------------------------------------------------------------

    public function test_saving_the_fake_couriers_page_never_touches_quadx_or_module_keys(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();
        CourierManager::register(FakeCourierFulfillment::class);

        $this->post($this->settingsUrl('/fake'), [
            'settings' => [
                'fake_enabled' => '1',
                'fake_mode' => 'production',
                'fake_note' => 'hello from fake',
                'fake_staging_token' => 'fake-staging-secret',
                // QuadX + module + junk keys smuggled into fake's form.
                'quadx_enabled' => '1',
                'quadx_staging_api_key' => 'smuggled-secret',
                'quadx_status_map' => ['delivered' => ['order_status_id' => '1']],
                'fulfillment_enabled' => '0',
                'not_a_real_setting' => 'nope',
            ],
        ])->assertRedirect();

        // Fake's own keys round-tripped (secret encrypted at rest).
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'fake_enabled', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'fake_mode', 'value' => 'production']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'fake_note', 'value' => 'hello from fake']);
        $stored = Setting::where('group', 'courier')->where('key', 'fake_staging_token')->value('value');
        $this->assertSame('fake-staging-secret', Crypt::decryptString($stored));

        // Nothing outside fake's own allowlist was written.
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_enabled']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_staging_api_key']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_status_map']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'fulfillment_enabled']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'not_a_real_setting']);
    }

    // ------------------------------------------------------------------
    // Masking + blank-keeps, unchanged semantics at the new location.
    // ------------------------------------------------------------------

    public function test_courier_page_masks_encrypted_values_and_blank_submission_keeps_them(): void
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

        // Blank re-save keeps the stored ciphertext byte-identical.
        $this->post($this->settingsUrl('/quadx'), [
            'settings' => ['quadx_staging_api_key' => '', 'quadx_mode' => 'staging'],
        ])->assertRedirect();

        $this->assertSame($stored, Setting::where('group', 'courier')->where('key', 'quadx_staging_api_key')->value('value'));
    }

    // ------------------------------------------------------------------
    // Status-map save round-trip from the new per-courier location.
    // ------------------------------------------------------------------

    public function test_status_map_round_trips_from_the_courier_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $delivered = OrderStatus::create(['store_id' => 1, 'name' => 'Delivered', 'sort_order' => 1, 'is_default' => true]);

        $this->post($this->settingsUrl('/quadx'), [
            'settings' => [
                'quadx_status_map' => [
                    'delivered' => ['order_status_id' => (string) $delivered->id, 'notify' => '1'],
                    'in_transit' => ['order_status_id' => '', 'notify' => '0'],
                ],
            ],
        ])->assertRedirect();

        $map = StatusMapper::currentMap('quadx');
        $this->assertSame((string) $delivered->id, (string) $map['delivered']['order_status_id']);

        // The saved selection renders back on the same page.
        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            ->assertSee('name="settings[quadx_status_map][delivered][order_status_id]"', false)
            ->assertSee('value="' . $delivered->id . '" selected', false);
    }

    // ------------------------------------------------------------------
    // Unknown courier key -> 404, GET and POST alike.
    // ------------------------------------------------------------------

    public function test_unknown_courier_key_is_a_404(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl('/nope'))->assertNotFound();
        $this->post($this->settingsUrl('/nope'), ['settings' => ['quadx_enabled' => '1']])->assertNotFound();
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_enabled']);
    }

    // ------------------------------------------------------------------
    // Task T1 (spx-courier plan): SPX is a REAL registered courier (not a
    // test-only fixture like FakeCourierFulfillment), so it needs no
    // register()/tearDown() dance — installExtension() alone is enough for
    // it to appear, exactly like QuadX.
    // ------------------------------------------------------------------

    public function test_spx_card_appears_on_the_hub_and_links_to_its_own_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('SPX')
            ->assertSee($this->settingsUrl('/spx'), false)
            // Disabled/unconfigured by default: no leaked settings key names.
            ->assertDontSee('name="settings[spx_mode]"', false);
    }

    public function test_spx_page_renders_its_schema_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertSee('name="settings[spx_enabled]"', false)
            ->assertSee('name="settings[spx_mode]"', false)
            ->assertSee('name="settings[spx_app_id]"', false)
            ->assertSee('name="settings[spx_secret_key]"', false)
            ->assertSee('name="settings[spx_user_id]"', false)
            ->assertSee('name="settings[spx_user_secret]"', false)
            ->assertSee('name="settings[spx_pouch_presets]"', false)
            ->assertSee('Test Connection')
            ->assertSee($this->settingsUrl('/spx/test-connection'), false)
            // Module fields belong to the hub, not here.
            ->assertDontSee('name="settings[fulfillment_enabled]"', false);
    }

    /**
     * Same W3 isolation guarantee FakeCourierFulfillment already proves, now
     * demonstrated with a REAL second courier: saving SPX's page can never
     * touch QuadX's keys or the module keys.
     */
    public function test_saving_the_spx_page_never_touches_quadx_or_module_keys(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl('/spx'), [
            'settings' => [
                'spx_enabled' => '1',
                'spx_mode' => 'uat',
                'spx_app_id' => '424242',
                'spx_pouch_presets' => '[]',
                // QuadX + module + junk keys smuggled into SPX's form.
                'quadx_enabled' => '1',
                'quadx_staging_api_key' => 'smuggled-secret',
                'quadx_status_map' => ['delivered' => ['order_status_id' => '1']],
                'fulfillment_enabled' => '0',
                'not_a_real_setting' => 'nope',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'spx_enabled', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'spx_mode', 'value' => 'uat']);
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);

        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_enabled']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_staging_api_key']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'quadx_status_map']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'fulfillment_enabled']);
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'not_a_real_setting']);
    }

    /** spx_secret_key / spx_user_secret round-trip encrypted, masked in the UI, same as QuadX's password fields. */
    public function test_spx_encrypted_fields_round_trip_masked_and_blank_keeps_them(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl('/spx'), [
            'settings' => ['spx_secret_key' => 'super-secret-app-key-1234'],
        ])->assertRedirect();

        $stored = Setting::where('group', 'courier')->where('key', 'spx_secret_key')->value('value');
        $this->assertNotSame('super-secret-app-key-1234', $stored);
        $this->assertSame('super-secret-app-key-1234', Crypt::decryptString($stored));

        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertSee('1234')
            ->assertDontSee('super-secret-app-key-1234');

        $this->post($this->settingsUrl('/spx'), [
            'settings' => ['spx_secret_key' => '', 'spx_mode' => 'uat'],
        ])->assertRedirect();

        $this->assertSame($stored, Setting::where('group', 'courier')->where('key', 'spx_secret_key')->value('value'));
    }

    /**
     * testConnection() must be graceful (never a 500) when credentials are
     * missing — the settings page's "Test Connection" button hits this
     * route directly, and an admin will click it long before entering real
     * SPX credentials.
     */
    public function test_spx_test_connection_is_graceful_without_credentials(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->postJson($this->settingsUrl('/spx/test-connection'))
            ->assertOk()
            ->assertJson(['ok' => false])
            ->assertJsonPath('message', 'App ID / App Secret not set.');

        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);

        $this->postJson($this->settingsUrl('/spx/test-connection'))
            ->assertOk()
            ->assertJson(['ok' => false])
            ->assertJsonPath('message', 'User ID / User Secret not set.');
    }

    // ------------------------------------------------------------------
    // Old combined-page URL still 301s to the hub (house-convention legacy
    // map, routes/admin/redirects.php) — a bookmarked anchor into the old
    // combined page lands on the hub, whose cards link onward per courier.
    // ------------------------------------------------------------------

    public function test_old_combined_page_url_301s_to_the_hub(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/' . $this->prefix . '/fulfillment/settings');

        $response->assertStatus(301);
        $this->assertStringContainsString(
            '/' . $this->prefix . '/settings/modules/courier-fulfillment',
            $response->headers->get('Location')
        );
    }
}
