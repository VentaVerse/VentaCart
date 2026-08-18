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
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeCourierFulfillment;
use Tests\TestCase;

/**
 * Task W3 of the Fulfillment Workflow v2 + SPX plan: the single combined
 * courier settings page split into a HUB (module-level settings + one card
 * per registered courier) and PER-COURIER PAGES at
 * extensions/courier-fulfillment/{courier}. See
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
        // One home: the hub itself sits at /settings; couriers and the
        // other literals are root-level siblings.
        return '/' . $this->prefix . '/extensions/courier-fulfillment' . ($suffix === '' ? '/settings' : $suffix);
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

    public function test_hub_menu_lists_each_courier_and_its_page_carries_state(): void
    {
        // Owner ruling 2026-08-19: courier cards became menu entries; the
        // hub carries the configuration menu, each courier page its own
        // state.
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('Status Mapping')
            ->assertSee('Pickup Addresses')
            ->assertSee($this->settingsUrl('/quadx'), false);

        // Unconfigured: staging mode (schema default), creds missing on
        // the courier's own page.
        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            ->assertSee('Disabled')
            ->assertSee('Staging')
            ->assertSee('2 of 2 credentials missing');

        // Enabled + both staging credentials stored -> complete.
        Setting::create(['group' => 'courier', 'key' => 'quadx_enabled', 'value' => '1']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_api_key', 'value' => Crypt::encryptString('k')]);
        Setting::create(['group' => 'courier', 'key' => 'quadx_staging_secret_key', 'value' => Crypt::encryptString('s')]);

        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            ->assertSee('Enabled')
            ->assertSee('Credentials set');
    }

    public function test_webhook_urls_live_on_their_couriers_own_page(): void
    {
        // Owner ruling 2026-08-19: each webhook URL sits with its courier,
        // not on the hub. LBC (no push API) renders none.
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertDontSee('/webhooks/courier/quadx?token=', false);

        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            ->assertSee('/webhooks/courier/quadx?token=', false);

        $this->get($this->settingsUrl('/lbc'))
            ->assertOk()
            ->assertDontSee('/webhooks/courier/', false);
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
    // Task T4: UAT-mode note card — visible only in UAT mode, names the
    // whitelist guide's path (the lighter mechanism per the brief — no new
    // authenticated download route for a repo-only PDF).
    // ------------------------------------------------------------------

    public function test_spx_uat_note_names_the_whitelist_guide_only_in_uat_mode(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        Setting::create(['group' => 'courier', 'key' => 'spx_mode', 'value' => 'uat']);

        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertSee('NSS OpenAPI UAT Order Creation Guide.pdf')
            ->assertSee('UAT mode');

        // A courier that isn't SPX never gets this note.
        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            ->assertDontSee('NSS OpenAPI UAT Order Creation Guide.pdf');
    }

    // ------------------------------------------------------------------
    // Task T4: "Create shipper account" — only rendered while spx_user_id
    // is empty, round-trips 3.1 Create Account via Http::fake, stores the
    // pair encrypted the same way every other SPX secret is stored.
    // ------------------------------------------------------------------

    public function test_create_shipper_account_button_only_renders_while_user_id_is_empty(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertSee('Create shipper account')
            ->assertSee($this->settingsUrl('/spx/create-account'), false);

        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);

        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertDontSee('Create shipper account');
    }

    /**
     * The default pickup address SPX reads its account-creation phone from.
     * It used to be settings(group=shipping, key=pickup_contact_number);
     * the module now owns the origin, and the number is unchanged.
     */
    private function configurePickupOrigin(): void
    {
        $country = \App\Models\Country::firstOrCreate(
            ['iso2' => 'PH'],
            ['iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => true]
        );

        \Extensions\CourierFulfillment\Models\PickupAddress::create([
            'label'         => 'Store pickup',
            'contact_name'  => 'Warehouse Team',
            'contact_phone' => '09171112222',
            'address_1'     => '456 Warehouse Ave',
            'barangay'      => 'Bel-Air',
            'district'      => null,
            'city'          => 'Makati',
            'state'         => 'Metro Manila',
            'postcode'      => '1200',
            'country_id'    => $country->id,
        ]);
    }

    public function test_create_shipper_account_rejects_without_app_credentials_or_a_pickup_phone(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl('/spx/create-account'))->assertRedirect();
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'spx_user_id']);

        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);

        // App credentials present, but no pickup address on file yet.
        $this->post($this->settingsUrl('/spx/create-account'))->assertRedirect();
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'spx_user_id']);
    }

    public function test_create_shipper_account_round_trips_via_http_fake_and_stores_the_pair_encrypted(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        $this->configurePickupOrigin();

        Http::fake([
            '*account/create*' => Http::response([
                'ret_code' => 0, 'message' => 'success.',
                'data' => ['user_id' => 110592440436149, 'user_secret' => 'd32e4ddc-c979-44a2-bf21-789a80c551d5', 'password' => 'dkjfdkjkdj'],
            ]),
        ]);

        $response = $this->post($this->settingsUrl('/spx/create-account'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'spx_user_id', 'value' => '110592440436149']);

        $storedSecret = Setting::where('group', 'courier')->where('key', 'spx_user_secret')->value('value');
        $this->assertNotSame('d32e4ddc-c979-44a2-bf21-789a80c551d5', $storedSecret);
        $this->assertSame('d32e4ddc-c979-44a2-bf21-789a80c551d5', Crypt::decryptString($storedSecret));

        // The request SPX actually received carried the normalized pickup phone.
        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'account/create')
                && $request['phone'] === '09171112222';
        });

        // Once a User ID exists, the button disappears from the page.
        $this->get($this->settingsUrl('/spx'))->assertOk()->assertDontSee('Create shipper account');
    }

    public function test_create_shipper_account_is_graceful_on_an_spx_error(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        $this->configurePickupOrigin();

        Http::fake([
            '*account/create*' => Http::response(['ret_code' => 1009, 'message' => 'Signature expired.', 'data' => []]),
        ]);

        $response = $this->post($this->settingsUrl('/spx/create-account'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'spx_user_id']);
    }

    /**
     * Review fix (Important): "only while spx_user_id is empty" was UI-only
     * — the settings page just doesn't render the button once a User ID is
     * on file, but nothing stopped a direct POST (a stale tab, a
     * double-submitted form) from calling SPX and REPLACING the stored
     * user_id/user_secret, orphaning whatever account existing bookings
     * were made under. This proves the server-side guard: with a User ID
     * already on file, a direct POST makes NO outbound HTTP call and
     * changes NOTHING — not the stored credentials, not any other setting.
     */
    public function test_create_shipper_account_refuses_a_direct_post_when_a_user_id_is_already_on_file(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        $originalSecret = Crypt::encryptString('original-user-secret-not-real');
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => $originalSecret]);
        $this->configurePickupOrigin();

        // Http::fake() with NO stub registered — any outbound call this
        // test didn't anticipate throws, so Http::assertNothingSent() below
        // is a genuine proof, not just an unasserted absence.
        Http::fake();

        $response = $this->post($this->settingsUrl('/spx/create-account'));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        Http::assertNothingSent();

        // Nothing changed: same user_id, same (still-encrypted) secret.
        $this->assertDatabaseHas('settings', ['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        $this->assertSame($originalSecret, Setting::where('group', 'courier')->where('key', 'spx_user_secret')->value('value'));
    }

    /** A courier without createShipperAccount() (e.g. QuadX) gets a clear error, never a 404 or a fatal. */
    public function test_create_shipper_account_route_is_graceful_for_a_courier_that_does_not_support_it(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->post($this->settingsUrl('/quadx/create-account'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ------------------------------------------------------------------
    // Task T4: structured pouch-preset editor — replaces T1's raw-JSON
    // text field with Alpine rows, serializing to the SAME setting key and
    // JSON shape (no data-format change, no controller/allowlist change).
    // ------------------------------------------------------------------

    /**
     * The editor's seed state is embedded via Illuminate\Support\Js::from(),
     * which hex-escapes every quote (" -> ") for safe HTML-attribute
     * embedding — so a literal '"label":"Small"' substring never appears in
     * the response body. This computes the ACTUAL escaped fragment
     * Js::from() would produce for a given key/value pair, so assertions
     * below match the real rendered bytes instead of guessing at them.
     */
    private function jsFragment(string $key, mixed $value): string
    {
        $encoded = \Illuminate\Support\Js::from([$key => $value])->toHtml();
        // Js::from() wraps the whole thing as JSON.parse('{...}') — strip
        // that wrapper and the surrounding braces to get just "key":value.
        return trim(substr($encoded, strlen("JSON.parse('{"), -strlen("}')")));
    }

    public function test_pouch_preset_editor_seeds_from_defaults_and_serializes_to_the_hidden_field(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // Unconfigured: seeded from SpxFulfillment::DEFAULT_POUCH_PRESETS.
        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertSee('name="settings[spx_pouch_presets]"', false)
            ->assertSee($this->jsFragment('label', 'Small'), false)
            ->assertSee($this->jsFragment('length_cm', 25), false)
            ->assertSee($this->jsFragment('label', 'Medium'), false)
            ->assertSee($this->jsFragment('label', 'Large'), false);

        // A stored custom set seeds the editor from THAT value instead.
        Setting::create(['group' => 'courier', 'key' => 'spx_pouch_presets', 'value' => json_encode([
            ['label' => 'Tiny', 'length_cm' => 10, 'width_cm' => 8, 'height_cm' => 5, 'max_weight_kg' => 1],
        ])]);

        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertSee($this->jsFragment('label', 'Tiny'), false)
            ->assertDontSee($this->jsFragment('label', 'Small'), false);
    }

    /**
     * The editor's hidden input posts JSON.stringify(presets) under the
     * EXACT SAME settings[spx_pouch_presets] name the old raw-JSON text
     * field used — proving the storage format/allowlist truly didn't
     * change, by posting the shape the client-side JS would produce and
     * confirming it round-trips byte-identically to how a hand-typed JSON
     * string always has.
     */
    public function test_pouch_preset_editor_round_trips_through_the_same_setting_key_and_format(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $presets = [
            ['label' => 'Small', 'length_cm' => 20, 'width_cm' => 15, 'height_cm' => 8, 'max_weight_kg' => 2],
            ['label' => 'Bulky', 'length_cm' => 60, 'width_cm' => 40, 'height_cm' => 30, 'max_weight_kg' => 15],
        ];

        $this->post($this->settingsUrl('/spx'), [
            'settings' => ['spx_pouch_presets' => json_encode($presets)],
        ])->assertRedirect();

        $stored = Setting::where('group', 'courier')->where('key', 'spx_pouch_presets')->value('value');
        $this->assertSame(json_encode($presets), $stored);

        // Reflected back into the editor's seed state on the next load.
        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertSee($this->jsFragment('label', 'Bulky'), false)
            ->assertSee($this->jsFragment('length_cm', 60), false);
    }

    // ------------------------------------------------------------------
    // Owner task (2026-08-08, "SPX status from code to their actual
    // meaning"): the status-mapping table shows the documented human label
    // (SpxFulfillment::STATUS_LABELS) instead of the bare code — available
    // immediately (no Refresh click needed) via SpxFulfillment::
    // statusLabels(), and the "Refresh from SPX" button still populates
    // spx_status_labels exactly like QuadX's live-refresh flow.
    // ------------------------------------------------------------------

    public function test_spx_settings_page_shows_the_twelve_documented_status_labels_without_a_refresh(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->settingsUrl('/spx'));
        $response->assertOk();

        foreach (\Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment::STATUS_LABELS as $code => $label) {
            $response->assertSee($label);
            $response->assertSee($code);
        }

        // The raw, unmapped code alone (e.g. bare "1001" with no label text
        // beside it) is never the whole story anymore — it still appears as
        // the muted secondary line, but the human label is what's primary.
        $response->assertSeeInOrder(['Pending Pickup', '1001']);
    }

    public function test_spx_refresh_statuses_action_populates_the_status_labels_setting(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'spx_status_labels']);

        $response = $this->post($this->settingsUrl('/spx/refresh-statuses'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $stored = Setting::where('group', 'courier')->where('key', 'spx_status_labels')->value('value');
        $this->assertNotNull($stored);
        $decoded = json_decode($stored, true);
        $this->assertSame(
            \Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment::STATUS_LABELS,
            $decoded
        );

        // Still renders correctly from the cache afterwards.
        $this->get($this->settingsUrl('/spx'))
            ->assertOk()
            ->assertSee('Pending Pickup');
    }

    /** An unmapped/unknown status code falls back gracefully to a title-cased version of itself, never a blank or an error. */
    public function test_spx_unknown_status_code_falls_back_to_the_raw_code_gracefully(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        // Simulate a courier vocabulary addition SPX made that isn't in our
        // curated mappableStatuses()/STATUS_LABELS yet by directly probing
        // the fallback chain CourierSettingsController::show() builds: any
        // code genuinely outside STATUS_LABELS still renders (ucwords of the
        // raw string), never a missing-key error. mappableStatuses() itself
        // is a closed, curated list, so this proves the CONTROLLER's
        // fallback chain is graceful rather than asserting a code that can
        // never actually appear on the rendered page.
        $courier = new \Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment();
        $labels = $courier->statusLabels();
        $this->assertArrayNotHasKey('9999', $labels);
    }

    /** QuadX (no statusLabels() accessor) keeps its pre-existing cache-then-ucwords behavior unchanged. */
    public function test_quadx_status_label_behavior_is_unchanged_by_the_spx_accessor(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->get($this->settingsUrl('/quadx'))
            ->assertOk()
            // QuadX's curated statuses ucwords-fallback exactly as before —
            // e.g. 'for_pickup' -> 'For Pickup' — since QuadX has no cached
            // spx_status_labels-equivalent setting yet and no statusLabels()
            // accessor of its own.
            ->assertSee('For Pickup')
            ->assertSee('Out For Delivery');
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
            '/' . $this->prefix . '/extensions/courier-fulfillment',
            $response->headers->get('Location')
        );
    }
}
