<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\CourierManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\FakeCourierFulfillment;
use Tests\TestCase;

/**
 * Task 4 of the fulfillment-polish plan: schema-driven per-courier settings.
 * See docs/superpowers/plans/2026-08-08-fulfillment-polish.md (Task 4) and
 * extensions/courier-fulfillment/src/Contracts/CourierFulfillmentInterface.php's
 * settingsSchema() docblock for the contract this exercises.
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
}
