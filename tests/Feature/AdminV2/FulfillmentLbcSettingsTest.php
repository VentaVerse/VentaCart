<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LBC, registered for its settings page and nothing else (owner directive,
 * 2026-08-14: "Create the settings UI first, I'll drop the keys, once drop,
 * you can test it").
 *
 * Four things this file exists to pin, because all four are easy to break
 * later by accident:
 *
 *  1. LBC can never be enabled and never looks bookable. isEnabled() is
 *     false whatever the toggle and the keys say, and the booking modal
 *     therefore renders it in its existing inert "Not configured" state.
 *  2. Nothing here calls LBC. Http::preventStrayRequests() is on for the
 *     whole file, so a future adapter that starts reaching out from a
 *     settings-page code path fails this suite instead of spending the
 *     owner's real subscription quota.
 *  3. The auth header is per API, not uniform (Pickup Request answers on
 *     Ocp-Apim-Subscription-Key, the other three on lbcOAkey). A wrong
 *     header returns a 401 that names the KEY, so this is pinned in a test
 *     rather than left to be rediscovered against the live gateway.
 *  4. Test versus live is a key, not a host, and the page must therefore
 *     hold both halves and send only one.
 */
class FulfillmentLbcSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');

        // No LBC endpoint may be touched from anywhere in this file: there
        // are no keys yet, and a stray request would go to the live gateway.
        Http::preventStrayRequests();
    }

    /**
     * Raw URL strings, not route(): the extension's routes register on the
     * first request after install (same convention as FulfillmentSettingsTest).
     */
    private function settingsUrl(string $suffix = ''): string
    {
        return '/' . $this->prefix . '/settings/modules/courier-fulfillment' . $suffix;
    }

    private function installExtension(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install('courier-fulfillment');
        $manager->bootEnabled();
    }

    /** Every key the schema declares, in schema order. @return string[] */
    private function schemaKeys(): array
    {
        return array_column((new LbcFulfillment())->settingsSchema(), 'key');
    }

    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------

    public function test_lbc_is_registered_and_resolves_by_key(): void
    {
        $this->installExtension();

        $lbc = CourierManager::get('lbc');

        $this->assertInstanceOf(LbcFulfillment::class, $lbc);
        $this->assertSame('lbc', $lbc->getKey());
        // "LBC", not "LBC Express": core's shipping_couriers row carries the
        // long name for the MANUAL path, and two identically named cards in
        // one modal would be a puzzle.
        $this->assertSame('LBC', $lbc->getName());
    }

    // ------------------------------------------------------------------
    // The two honesty guarantees
    // ------------------------------------------------------------------

    public function test_lbc_is_never_enabled_even_fully_credentialed(): void
    {
        $this->installExtension();

        Setting::create(['group' => 'courier', 'key' => 'lbc_enabled', 'value' => '1']);
        foreach ($this->schemaKeys() as $key) {
            if (str_ends_with($key, '_key')) {
                Setting::create(['group' => 'courier', 'key' => $key, 'value' => Crypt::encryptString('filled')]);
            }
        }

        $lbc = CourierManager::get('lbc');

        $this->assertFalse($lbc->isEnabled());
        // The flag itself is still real: it is what puts LBC in the booking
        // modal's list at all, as the inert card.
        $this->assertTrue(CourierManager::isFlagged('lbc'));
        $this->assertNotContains('lbc', array_map(
            fn ($c) => $c->getKey(),
            CourierManager::enabled()
        ));
        $this->assertContains('lbc', array_map(
            fn ($c) => $c->getKey(),
            CourierManager::flagged()
        ));
    }

    public function test_configuration_issues_name_each_missing_key_and_always_the_missing_adapter(): void
    {
        $this->installExtension();
        $lbc = CourierManager::get('lbc');

        // Nothing stored at all: one fact for the keys, not four labels,
        // because the booking modal's card gives its artwork whatever room
        // the words leave. The merchant constants and the tracking series are
        // named individually, each being a separate thing to go and get.
        $this->assertSame(
            [
                LbcFulfillment::NO_KEYS_AT_ALL,
                'Client code',
                'Shipper Account No',
                LbcFulfillment::NO_TRACKING_SERIES,
                LbcFulfillment::NOT_BUILT,
            ],
            $lbc->configurationIssues()
        );

        // Partial configuration is the normal state (LBC approves each
        // subscription separately), so the moment one key lands the rest are
        // named individually.
        Setting::create(['group' => 'courier', 'key' => 'lbc_test_airwaybill_key', 'value' => Crypt::encryptString('k')]);

        $issues = $lbc->configurationIssues();
        $this->assertNotContains(LbcFulfillment::NO_KEYS_AT_ALL, $issues);
        $this->assertNotContains('Air Waybill key', $issues);
        $this->assertContains('Common API key', $issues);
        $this->assertContains('Track and Trace key', $issues);
        $this->assertContains('Pickup Request key', $issues);

        // The adapter entry never leaves, whatever is filled in.
        foreach ($this->schemaKeys() as $key) {
            Setting::updateOrCreate(
                ['group' => 'courier', 'key' => $key],
                ['value' => str_ends_with($key, '_key') ? Crypt::encryptString('k') : 'filled']
            );
        }
        $this->assertSame([LbcFulfillment::NOT_BUILT], $lbc->configurationIssues());
    }

    /**
     * Only the mode being used is reported. Both halves carry the same four
     * labels, so naming both would print "Air Waybill key" twice and say
     * nothing about which one to go and get.
     */
    public function test_configuration_issues_follow_the_active_mode(): void
    {
        $this->installExtension();

        foreach (['common', 'airwaybill', 'pickup', 'track'] as $api) {
            Setting::create([
                'group' => 'courier',
                'key'   => 'lbc_test_' . $api . '_key',
                'value' => Crypt::encryptString('t'),
            ]);
        }

        // Test mode: all four of its keys are on file, so none is named.
        $issues = (new LbcFulfillment())->configurationIssues();
        $this->assertNotContains(LbcFulfillment::NO_KEYS_AT_ALL, $issues);
        $this->assertNotContains('Air Waybill key', $issues);

        // Switching to live must not inherit them: a different set of keys is
        // a different set of missing keys.
        Setting::create(['group' => 'courier', 'key' => 'lbc_mode', 'value' => 'live']);
        $this->assertContains(LbcFulfillment::NO_KEYS_AT_ALL, (new LbcFulfillment())->configurationIssues());
    }

    /** Both ends of the block are needed, because half a range allocates nothing. */
    public function test_tracking_series_is_outstanding_until_both_ends_are_known(): void
    {
        $this->installExtension();

        Setting::create(['group' => 'courier', 'key' => 'lbc_tracking_series_start', 'value' => '28310000000040']);
        $issues = (new LbcFulfillment())->configurationIssues();
        $this->assertContains(LbcFulfillment::NO_TRACKING_SERIES, $issues);
        // Named once, not once per end.
        $this->assertSame(1, count(array_keys($issues, LbcFulfillment::NO_TRACKING_SERIES, true)));

        Setting::create(['group' => 'courier', 'key' => 'lbc_tracking_series_end', 'value' => '28310000000140']);
        $this->assertNotContains(LbcFulfillment::NO_TRACKING_SERIES, (new LbcFulfillment())->configurationIssues());
    }

    public function test_every_operation_refuses_honestly_and_calls_nothing(): void
    {
        $this->installExtension();
        $lbc = CourierManager::get('lbc');
        $order = new \App\Models\Order();

        $this->assertFalse($lbc->createShipment($order, [])['success']);
        $this->assertFalse($lbc->cancelShipment('X')['success']);
        $this->assertFalse($lbc->getTracking('X')['success']);
        $this->assertFalse($lbc->printLabel('X', 'zebra')['success']);
        $this->assertFalse($lbc->testConnection()['ok']);
        $this->assertFalse($lbc->refreshStatusLabels()['ok']);

        $this->assertNull($lbc->resolvePendingCreation($order));
        $this->assertNull($lbc->estimateFee($order, []));
        $this->assertSame([], $lbc->parseWebhook(['anything']));
        $this->assertSame([], $lbc->trackingLinks($order));
        $this->assertSame([], $lbc->getPickupSlots($order));
        $this->assertSame([], $lbc->getServiceTypes());
        $this->assertSame([], $lbc->getParcelOptions());
        $this->assertSame([], $lbc->getShippingPaymentOptions());
        $this->assertSame([], $lbc->terminalStatuses());
        $this->assertSame([], $lbc->cancelableStatuses());
        $this->assertSame([], $lbc->mappableStatuses());

        // The sanctioned "we did not ask" default, not a refusal: LBC's
        // validateServicableArea exists but nothing calls it yet.
        $this->assertSame(
            \Extensions\CourierFulfillment\Support\ServiceabilityVerdict::UNKNOWN,
            $lbc->checkServiceability($order)->status
        );
    }

    // ------------------------------------------------------------------
    // The gateway, one host for both modes
    // ------------------------------------------------------------------

    public function test_gateway_url_defaults_and_is_overridable_with_one_trailing_slash(): void
    {
        $this->installExtension();
        $lbc = new LbcFulfillment();

        $this->assertSame('https://lbcapigateway.lbcapps.com/', $lbc->gatewayUrl());

        Setting::create(['group' => 'courier', 'key' => 'lbc_gateway_url', 'value' => 'https://elsewhere.example/api']);
        $this->assertSame('https://elsewhere.example/api/', (new LbcFulfillment())->gatewayUrl());

        Setting::where('key', 'lbc_gateway_url')->update(['value' => 'https://elsewhere.example/api///']);
        $this->assertSame('https://elsewhere.example/api/', (new LbcFulfillment())->gatewayUrl());
    }

    // ------------------------------------------------------------------
    // Test versus live is a key, not a host
    // ------------------------------------------------------------------

    /**
     * The mode select exists, offers exactly the two environments LBC has,
     * and carries the "_mode" suffix the settings page keys its mode-scoped
     * groups off.
     */
    public function test_schema_declares_a_mode_field_with_test_and_live(): void
    {
        $mode = collect((new LbcFulfillment())->settingsSchema())->firstWhere('key', 'lbc_mode');

        $this->assertNotNull($mode);
        $this->assertSame('select', $mode['type']);
        $this->assertSame(['test', 'live'], array_column($mode['options'], 'value'));
        $this->assertSame(['Test', 'Live'], array_column($mode['options'], 'label'));
        // Test first: the settings page and the hub both fall back to the
        // first option, and an unconfigured store must not default to live.
        $this->assertSame('test', $mode['options'][0]['value']);
        $this->assertSame(LbcFulfillment::DEFAULT_MODE, $mode['options'][0]['value']);
    }

    /** Four APIs, two modes, eight keys, every one of them encrypted at rest. */
    public function test_every_api_has_a_key_in_each_mode_and_no_app_key_survives(): void
    {
        $schema = (new LbcFulfillment())->settingsSchema();

        foreach (['test', 'live'] as $mode) {
            $scoped = array_values(array_filter(
                $schema,
                fn (array $f) => ($f['mode_scoped'] ?? null) === $mode
            ));

            $this->assertSame([
                'lbc_' . $mode . '_common_key',
                'lbc_' . $mode . '_airwaybill_key',
                'lbc_' . $mode . '_pickup_key',
                'lbc_' . $mode . '_track_key',
            ], array_column($scoped, 'key'));

            foreach ($scoped as $field) {
                $this->assertTrue($field['encrypted'], $field['key'] . ' must be encrypted at rest');
                $this->assertSame('secret', $field['type']);
            }
        }

        // Disproven live: a pickup was created with the <AppKey> node removed
        // entirely, so a field for it would be a box nobody can fill right.
        $this->assertNotContains('lbc_pickup_app_key', $this->schemaKeys());

        // The two merchant constants are identifiers, not credentials, so
        // they are plain text and never masked behind a View button.
        foreach (['lbc_client_code', 'lbc_shipper_account_no'] as $key) {
            $field = collect($schema)->firstWhere('key', $key);
            $this->assertNotNull($field, $key . ' is missing from the schema');
            $this->assertSame('text', $field['type']);
            $this->assertEmpty($field['encrypted'] ?? false);
        }
    }

    public function test_mode_defaults_to_test_and_rejects_anything_it_does_not_know(): void
    {
        $this->installExtension();

        $this->assertSame('test', (new LbcFulfillment())->mode());

        Setting::create(['group' => 'courier', 'key' => 'lbc_mode', 'value' => 'live']);
        $this->assertSame('live', (new LbcFulfillment())->mode());

        // Never quietly go live on a value nobody recognises.
        Setting::where('key', 'lbc_mode')->update(['value' => 'production']);
        $this->assertSame('test', (new LbcFulfillment())->mode());
    }

    public function test_the_active_mode_decides_which_key_is_read(): void
    {
        $this->installExtension();

        Setting::create(['group' => 'courier', 'key' => 'lbc_test_pickup_key', 'value' => Crypt::encryptString('secondary')]);
        Setting::create(['group' => 'courier', 'key' => 'lbc_live_pickup_key', 'value' => Crypt::encryptString('primary')]);

        $lbc = new LbcFulfillment();
        $this->assertSame('lbc_test_pickup_key', $lbc->keySetting('pickup'));
        $this->assertSame('secondary', $lbc->subscriptionKey('pickup'));

        Setting::create(['group' => 'courier', 'key' => 'lbc_mode', 'value' => 'live']);
        $lbc = new LbcFulfillment();
        $this->assertSame('lbc_live_pickup_key', $lbc->keySetting('pickup'));
        $this->assertSame('primary', $lbc->subscriptionKey('pickup'));

        // An unset key is null, not an empty string that would be sent as a
        // header value and answered with a misleading 401.
        $this->assertNull($lbc->subscriptionKey('track'));
    }

    /**
     * The single most expensive fact about LBC: the auth header is NOT the
     * same for all four APIs, and the 401 for a wrong one reads like a bad
     * key. Verified live 2026-08-14, see docs/lbc/integration-notes.md.
     */
    public function test_the_auth_header_is_per_api(): void
    {
        $lbc = new LbcFulfillment();

        $this->assertSame('Ocp-Apim-Subscription-Key', $lbc->authHeader('pickup'));
        $this->assertSame('lbcOAkey', $lbc->authHeader('common'));
        $this->assertSame('lbcOAkey', $lbc->authHeader('airwaybill'));
        $this->assertSame('lbcOAkey', $lbc->authHeader('track'));

        // Guessing a header would produce that same misleading 401, so an
        // unknown API is refused loudly instead.
        $this->expectException(\InvalidArgumentException::class);
        $lbc->authHeader('nope');
    }

    // ------------------------------------------------------------------
    // The page
    // ------------------------------------------------------------------

    public function test_settings_page_renders_every_field_and_no_status_mapping_table(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $response = $this->get($this->settingsUrl('/lbc'))->assertOk();

        foreach ($this->schemaKeys() as $key) {
            $response->assertSee('settings[' . $key . ']', false);
        }

        $response
            ->assertSee('Air Waybill key')
            ->assertSee('Common API key')
            // Both mode groups are in the document; the page shows one at a
            // time (x-show on the mode select), so both must render.
            ->assertSee('Test credentials')
            ->assertSee('Live credentials')
            ->assertSee('Client code')
            ->assertSee('Shipper Account No')
            // The disproven field is gone from the page as well as the schema.
            ->assertDontSee('AppKey')
            // The gateway default is visible in the empty box, so the
            // operator can see where calls would go without saving first.
            ->assertSee(LbcFulfillment::DEFAULT_GATEWAY_URL, false)
            // mappableStatuses() is empty, so the whole section is absent
            // rather than a table of headings over nothing.
            ->assertDontSee('lbc_status_map', false)
            ->assertDontSee('Order status mapping')
            // No push API, so no webhook URL is offered for LBC anywhere.
            ->assertDontSee('/webhooks/courier/lbc', false);

        $this->get($this->settingsUrl())
            ->assertOk()
            ->assertSee('LBC')
            ->assertDontSee('/webhooks/courier/lbc', false);
    }

    public function test_saving_encrypts_every_key_and_touches_no_other_courier(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        $this->post($this->settingsUrl('/lbc'), [
            'settings' => [
                'lbc_enabled'             => '1',
                'lbc_mode'                => 'live',
                'lbc_gateway_url'         => 'https://lbcapigateway.lbcapps.com/',
                'lbc_client_code'         => 'TEST',
                'lbc_shipper_account_no'  => '2015062300003',
                'lbc_test_common_key'     => 'test-common',
                'lbc_test_airwaybill_key' => 'test-awb',
                'lbc_test_pickup_key'     => 'test-pickup',
                'lbc_test_track_key'      => 'test-track',
                'lbc_live_common_key'     => 'live-common',
                'lbc_live_airwaybill_key' => 'live-awb',
                'lbc_live_pickup_key'     => 'live-pickup',
                'lbc_live_track_key'      => 'live-track',
                // Smuggled: another courier's key and a module key. The
                // per-courier allowlist must discard both.
                'quadx_enabled'         => '1',
                'spx_app_id'            => 'nope',
                'fulfillment_enabled'   => '0',
                'lbc_webhook_token'     => 'forged',
                // The retired field: no longer in the schema, so no longer
                // writable, whatever a stale form posts.
                'lbc_pickup_app_key'    => 'appkey-secret',
            ],
        ])->assertRedirect();

        $stored = Setting::where('group', 'courier')->pluck('value', 'key');

        $this->assertSame('1', $stored['lbc_enabled']);
        $this->assertSame('live', $stored['lbc_mode']);
        $this->assertSame('https://lbcapigateway.lbcapps.com/', $stored['lbc_gateway_url']);
        $this->assertSame('TEST', $stored['lbc_client_code']);
        $this->assertSame('2015062300003', $stored['lbc_shipper_account_no']);
        $this->assertArrayNotHasKey('lbc_pickup_app_key', $stored->all());

        foreach ([
            'lbc_test_common_key'     => 'test-common',
            'lbc_test_airwaybill_key' => 'test-awb',
            'lbc_test_pickup_key'     => 'test-pickup',
            'lbc_test_track_key'      => 'test-track',
            'lbc_live_common_key'     => 'live-common',
            'lbc_live_airwaybill_key' => 'live-awb',
            'lbc_live_pickup_key'     => 'live-pickup',
            'lbc_live_track_key'      => 'live-track',
        ] as $key => $plain) {
            $this->assertNotSame($plain, $stored[$key], $key . ' was stored in the clear');
            $this->assertSame($plain, Crypt::decryptString($stored[$key]));
        }

        $this->assertArrayNotHasKey('quadx_enabled', $stored->all());
        $this->assertArrayNotHasKey('spx_app_id', $stored->all());
        $this->assertArrayNotHasKey('fulfillment_enabled', $stored->all());
        $this->assertArrayNotHasKey('lbc_webhook_token', $stored->all());
    }

    public function test_a_stored_key_is_masked_on_the_page_and_kept_when_resubmitted_blank(): void
    {
        $this->actingAs(User::factory()->create());
        $this->installExtension();

        Setting::create([
            'group' => 'courier',
            'key'   => 'lbc_test_airwaybill_key',
            'value' => Crypt::encryptString('abcdefgh1234'),
        ]);

        // Masked display, same shape the rest of this module uses: all but
        // the last four characters replaced.
        $this->get($this->settingsUrl('/lbc'))
            ->assertOk()
            ->assertSee(str_repeat('*', 8) . '1234');

        // The secret control posts nothing while resting, which must keep
        // the stored value rather than blank it.
        $this->post($this->settingsUrl('/lbc'), ['settings' => ['lbc_enabled' => '1']])
            ->assertRedirect();

        $this->assertSame(
            'abcdefgh1234',
            Crypt::decryptString(Setting::where('key', 'lbc_test_airwaybill_key')->value('value'))
        );
    }

    // ------------------------------------------------------------------
    // The one-off carry-over of the pre-mode keys
    // ------------------------------------------------------------------

    /** The four retired keys and the values they held. */
    private function seedLegacyKeys(): array
    {
        $plain = [
            'lbc_psgc_key'       => 'psgc-secondary',
            'lbc_airwaybill_key' => 'awb-secondary',
            'lbc_pickup_key'     => 'pickup-secondary',
            'lbc_track_key'      => 'track-secondary',
        ];

        foreach ($plain as $key => $value) {
            Setting::create(['group' => 'courier', 'key' => $key, 'value' => Crypt::encryptString($value)]);
        }

        return $plain;
    }

    /**
     * $this->artisan() reuses the console Kernel's memoized
     * Illuminate\Console\Application, built during setUp()'s own migrate run,
     * so a provider booted mid-test by installExtension() is too late for
     * ServiceProvider::commands() to reach it (see FulfillmentTest's
     * registerPollCommand() for the long version). Registering straight onto
     * the already-built instance sidesteps the ordering entirely.
     */
    private function registerMigrateCommand(): void
    {
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand(new \Extensions\CourierFulfillment\Console\Commands\MigrateLbcKeysToModes());
    }

    /**
     * The values already on file are the owner's SECONDARY keys, which LBC
     * treats as test, so they land in Test mode and still decrypt to exactly
     * what they decrypted to before: the ciphertext is moved, not re-typed.
     */
    public function test_the_one_off_command_moves_the_pre_mode_keys_into_test(): void
    {
        $this->registerMigrateCommand();
        $this->installExtension();
        $plain = $this->seedLegacyKeys();
        Setting::create(['group' => 'courier', 'key' => 'lbc_pickup_app_key', 'value' => Crypt::encryptString('dead')]);

        $this->artisan('fulfillment:lbc-migrate-keys')->assertSuccessful();

        foreach ([
            'lbc_test_common_key'     => $plain['lbc_psgc_key'],
            'lbc_test_airwaybill_key' => $plain['lbc_airwaybill_key'],
            'lbc_test_pickup_key'     => $plain['lbc_pickup_key'],
            'lbc_test_track_key'      => $plain['lbc_track_key'],
        ] as $key => $expected) {
            $stored = Setting::where('group', 'courier')->where('key', $key)->value('value');
            $this->assertNotNull($stored, $key . ' did not arrive');
            $this->assertNotSame($expected, $stored, $key . ' was written in the clear');
            $this->assertSame($expected, Crypt::decryptString($stored));
        }

        // Nothing is left behind to be read by mistake, the AppKey row least
        // of all.
        foreach (['lbc_psgc_key', 'lbc_airwaybill_key', 'lbc_pickup_key', 'lbc_track_key', 'lbc_pickup_app_key'] as $gone) {
            $this->assertFalse(
                Setting::where('group', 'courier')->where('key', $gone)->exists(),
                $gone . ' survived the move'
            );
        }

        // Test is the default mode, so the moved keys are the ones in use.
        $this->assertSame('test', (new LbcFulfillment())->mode());
        $this->assertSame($plain['lbc_pickup_key'], (new LbcFulfillment())->subscriptionKey('pickup'));
    }

    public function test_the_one_off_command_is_idempotent_and_never_overwrites(): void
    {
        $this->registerMigrateCommand();
        $this->installExtension();
        $this->seedLegacyKeys();

        // An operator's own paste wins over a legacy row.
        Setting::create(['group' => 'courier', 'key' => 'lbc_test_track_key', 'value' => Crypt::encryptString('pasted')]);

        $this->artisan('fulfillment:lbc-migrate-keys')->assertSuccessful();

        $this->assertSame(
            'pasted',
            Crypt::decryptString(Setting::where('key', 'lbc_test_track_key')->value('value'))
        );
        // …and the legacy row it refused to move is left where it is rather
        // than dropped.
        $this->assertTrue(Setting::where('key', 'lbc_track_key')->exists());

        // A second run finds nothing left to do and changes nothing.
        $before = Setting::where('group', 'courier')->pluck('value', 'key')->toArray();
        $this->artisan('fulfillment:lbc-migrate-keys')->assertSuccessful();
        $this->assertSame($before, Setting::where('group', 'courier')->pluck('value', 'key')->toArray());
    }

    public function test_the_one_off_command_dry_run_writes_nothing(): void
    {
        $this->registerMigrateCommand();
        $this->installExtension();
        $this->seedLegacyKeys();

        $before = Setting::where('group', 'courier')->pluck('value', 'key')->toArray();

        $this->artisan('fulfillment:lbc-migrate-keys', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, Setting::where('group', 'courier')->pluck('value', 'key')->toArray());
    }
}
