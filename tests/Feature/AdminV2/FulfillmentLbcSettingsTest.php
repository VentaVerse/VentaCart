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
 * Two things this file exists to pin, because both are easy to break later
 * by accident:
 *
 *  1. LBC can never be enabled and never looks bookable. isEnabled() is
 *     false whatever the toggle and the keys say, and the booking modal
 *     therefore renders it in its existing inert "Not configured" state.
 *  2. Nothing here calls LBC. Http::preventStrayRequests() is on for the
 *     whole file, so a future adapter that starts reaching out from a
 *     settings-page code path fails this suite instead of spending the
 *     owner's real subscription quota.
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

        // Nothing stored at all: one fact, not five labels, because the booking
        // modal's card gives its artwork whatever room the words leave.
        $this->assertSame(
            [LbcFulfillment::NO_KEYS_AT_ALL, LbcFulfillment::NOT_BUILT],
            $lbc->configurationIssues()
        );

        // Partial configuration is the normal state (LBC approves each
        // subscription separately), so the moment one key lands the rest are
        // named individually.
        Setting::create(['group' => 'courier', 'key' => 'lbc_airwaybill_key', 'value' => Crypt::encryptString('k')]);

        $issues = $lbc->configurationIssues();
        $this->assertNotContains(LbcFulfillment::NO_KEYS_AT_ALL, $issues);
        $this->assertNotContains('Air Waybill key', $issues);
        $this->assertContains('Corporate PSGC Lookup key', $issues);
        $this->assertContains('Track and Trace key', $issues);
        $this->assertContains('Pickup Request AppKey', $issues);

        // The adapter entry never leaves, whatever is filled in.
        foreach ($this->schemaKeys() as $key) {
            Setting::updateOrCreate(
                ['group' => 'courier', 'key' => $key],
                ['value' => Crypt::encryptString('k')]
            );
        }
        $this->assertSame([LbcFulfillment::NOT_BUILT], $lbc->configurationIssues());
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
    // The gateway, one field, no mode
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

    /**
     * No mode selector, and that is the finding rather than an omission:
     * all four LBC PDFs document a single gateway host and no sandbox/UAT
     * environment. A "_mode"-suffixed key would make the settings page
     * render mode-scoped credential groups that describe nothing.
     */
    public function test_schema_declares_no_mode_field(): void
    {
        foreach ($this->schemaKeys() as $key) {
            $this->assertStringEndsNotWith('_mode', $key);
        }
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
            ->assertSee('Pickup Request AppKey')
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
                'lbc_enabled'        => '1',
                'lbc_gateway_url'    => 'https://lbcapigateway.lbcapps.com/',
                'lbc_psgc_key'       => 'psgc-secret',
                'lbc_airwaybill_key' => 'awb-secret',
                'lbc_pickup_key'     => 'pickup-secret',
                'lbc_track_key'      => 'track-secret',
                'lbc_pickup_app_key' => 'appkey-secret',
                // Smuggled: another courier's key and a module key. The
                // per-courier allowlist must discard both.
                'quadx_enabled'         => '1',
                'spx_app_id'            => 'nope',
                'fulfillment_enabled'   => '0',
                'lbc_webhook_token'     => 'forged',
            ],
        ])->assertRedirect();

        $stored = Setting::where('group', 'courier')->pluck('value', 'key');

        $this->assertSame('1', $stored['lbc_enabled']);
        $this->assertSame('https://lbcapigateway.lbcapps.com/', $stored['lbc_gateway_url']);

        foreach ([
            'lbc_psgc_key'       => 'psgc-secret',
            'lbc_airwaybill_key' => 'awb-secret',
            'lbc_pickup_key'     => 'pickup-secret',
            'lbc_track_key'      => 'track-secret',
            'lbc_pickup_app_key' => 'appkey-secret',
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
            'key'   => 'lbc_airwaybill_key',
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
            Crypt::decryptString(Setting::where('key', 'lbc_airwaybill_key')->value('value'))
        );
    }
}
