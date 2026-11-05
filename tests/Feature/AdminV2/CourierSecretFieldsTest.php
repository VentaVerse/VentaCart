<?php

namespace Tests\Feature\AdminV2;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use Database\Seeders\RolePermissionSeeder;
use Extensions\CourierFulfillment\CourierFulfillmentServiceProvider;
use Extensions\CourierFulfillment\Support\CourierSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * The courier settings page's encrypted fields: shown as a mask, revealed
 * one at a time on request, replaced only by a typed value and cleared only
 * by an explicit Remove.
 */
class CourierSecretFieldsTest extends TestCase
{
    use RefreshDatabase;

    private const LBC_KEY = 'lbc_test_common_key';

    private const LBC_VALUE = 'demo-common-key-0001-3f9a';

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = config('admin.prefix', 'admin');

        // Nothing here may reach a courier.
        Http::preventStrayRequests();

        $manager = app(ExtensionManager::class);
        $manager->install('courier-fulfillment');
        $manager->bootEnabled();
    }

    /** Raw URLs: the extension's routes register on the first request after install. */
    private function url(string $suffix): string
    {
        return '/' . $this->prefix . '/extensions/courier-fulfillment' . $suffix;
    }

    private function revealUrl(string $courier, string $key): string
    {
        return $this->url('/' . $courier . '/reveal-secret') . '?key=' . urlencode($key);
    }

    private function store(string $key, string $plain): void
    {
        Setting::updateOrCreate(
            ['group' => 'courier', 'key' => $key],
            ['value' => Crypt::encryptString($plain)]
        );
    }

    private function stored(string $key): ?string
    {
        $value = Setting::where('group', 'courier')->where('key', $key)->value('value');

        return $value === null ? null : Crypt::decryptString($value);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    // ------------------------------------------------------------------
    // The page
    // ------------------------------------------------------------------

    public function test_the_page_carries_only_the_mask_of_every_saved_secret(): void
    {
        $this->admin();
        $this->store(self::LBC_KEY, self::LBC_VALUE);
        $this->store('lbc_live_track_key', 'demo-live-track-key-77c1');

        $html = $this->get($this->url('/lbc'))->assertOk()->getContent();

        $this->assertStringNotContainsString(self::LBC_VALUE, $html);
        $this->assertStringNotContainsString('demo-live-track-key-77c1', $html);
        // Not even a long run of the value: only its last four ride along.
        $this->assertStringNotContainsString('demo-common-key', $html);
        $this->assertStringContainsString('3f9a', $html);
        $this->assertStringContainsString('77c1', $html);
        $this->assertStringNotContainsString('Leave blank', $html);
        $this->assertStringContainsString('reveal-secret', $html);
        $this->assertStringContainsString('remove_secrets[]', $html);
    }

    public function test_quadx_and_spx_secrets_get_the_same_control(): void
    {
        $this->admin();
        $this->store('quadx_staging_api_key', 'demo-quadx-api-key-5e21');
        $this->store('spx_secret_key', 'demo-spx-app-secret-a0b9');

        $quadx = $this->get($this->url('/quadx'))->assertOk()->getContent();
        $this->assertStringNotContainsString('demo-quadx-api-key-5e21', $quadx);
        $this->assertStringContainsString('5e21', $quadx);
        $this->assertStringContainsString('vSecret', $quadx);

        $spx = $this->get($this->url('/spx'))->assertOk()->getContent();
        $this->assertStringNotContainsString('demo-spx-app-secret-a0b9', $spx);
        $this->assertStringContainsString('a0b9', $spx);
    }

    public function test_a_short_secret_shows_bullets_only(): void
    {
        $this->assertSame(str_repeat("\u{2022}", 16), CourierSecrets::mask('ab12'));
        $this->assertSame(str_repeat("\u{2022}", 16) . '5678', CourierSecrets::mask('demo5678'));
    }

    public function test_a_value_that_no_longer_decrypts_is_flagged_not_masked(): void
    {
        $this->admin();
        Setting::create(['group' => 'courier', 'key' => self::LBC_KEY, 'value' => 'not-an-encrypted-payload']);

        $this->get($this->url('/lbc'))
            ->assertOk()
            ->assertSee('\u0022unreadable\u0022:true', false)
            ->assertDontSee('not-an-encrypted-payload');

        $this->postJson($this->revealUrl('lbc', self::LBC_KEY))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.reason', 'unreadable');
    }

    // ------------------------------------------------------------------
    // The reveal endpoint
    // ------------------------------------------------------------------

    public function test_an_authorized_admin_gets_the_value_and_the_view_is_logged_without_it(): void
    {
        $user = $this->admin();
        $this->store(self::LBC_KEY, self::LBC_VALUE);
        $this->store('lbc_test_track_key', 'demo-track-key-other');
        Log::spy();

        $this->postJson($this->revealUrl('lbc', self::LBC_KEY))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'success' => true,
                'message' => 'Saved value.',
                'data' => ['value' => self::LBC_VALUE],
            ]);

        Log::shouldHaveReceived('info')->once()->with('Courier secret viewed.', Mockery::on(
            fn (array $context) => $context['field'] === self::LBC_KEY
                && $context['courier'] === 'lbc'
                && $context['user_id'] === $user->id
                && !in_array(self::LBC_VALUE, $context, true)
        ));
    }

    public function test_reveal_answers_only_for_this_couriers_own_secrets(): void
    {
        $this->admin();
        $this->store('quadx_staging_api_key', 'demo-quadx-api-key-5e21');
        Setting::create(['group' => 'courier', 'key' => 'lbc_client_code', 'value' => 'DEMO-001']);
        Setting::create(['group' => 'courier', 'key' => 'quadx_webhook_token', 'value' => 'demo-token']);

        // Another courier's secret, a plain field and a server-made token.
        $this->postJson($this->revealUrl('lbc', 'quadx_staging_api_key'))->assertStatus(422);
        $this->postJson($this->revealUrl('lbc', 'lbc_client_code'))->assertStatus(422);
        $this->postJson($this->revealUrl('quadx', 'quadx_webhook_token'))->assertStatus(422);
        $this->postJson($this->url('/lbc/reveal-secret'))->assertStatus(422);
        $this->postJson($this->revealUrl('nope', self::LBC_KEY))->assertNotFound();

        // A known field with nothing saved.
        $this->postJson($this->revealUrl('lbc', 'lbc_live_common_key'))
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_reveal_is_refused_to_admins_who_cannot_edit_courier_settings(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->store(self::LBC_KEY, self::LBC_VALUE);

        $this->actingAs(User::factory()->role('staff')->create());
        $this->postJson($this->revealUrl('lbc', self::LBC_KEY))
            ->assertForbidden()
            ->assertJsonMissing(['value' => self::LBC_VALUE]);

        // Seeing the extension screens is not enough: View needs edit.
        $viewer = Role::firstOrCreate(['slug' => 'extension-viewer'], ['name' => 'Extension viewer']);
        $viewer->permissions()->sync(Permission::where('slug', 'extensions.view')->pluck('id'));
        $this->actingAs(User::factory()->create(['role_id' => $viewer->id]));
        $this->get($this->url('/lbc'))->assertOk();
        $this->postJson($this->revealUrl('lbc', self::LBC_KEY))->assertForbidden();
    }

    public function test_reveal_needs_a_signed_in_admin(): void
    {
        $this->store(self::LBC_KEY, self::LBC_VALUE);

        $this->postJson($this->revealUrl('lbc', self::LBC_KEY))->assertUnauthorized();
    }

    public function test_reveal_without_a_csrf_token_is_refused(): void
    {
        $this->admin();
        $this->store(self::LBC_KEY, self::LBC_VALUE);

        // The CSRF check stands down under the 'testing' environment, so
        // the request runs as a real one would.
        $this->app['env'] = 'local';
        try {
            $this->postJson($this->revealUrl('lbc', self::LBC_KEY))
                ->assertStatus(419)
                ->assertJsonMissing(['value' => self::LBC_VALUE]);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_reveal_is_rate_limited_per_admin(): void
    {
        $this->admin();
        $this->store(self::LBC_KEY, self::LBC_VALUE);

        for ($i = 0; $i < CourierFulfillmentServiceProvider::SECRET_REVEALS_PER_MINUTE; $i++) {
            $this->postJson($this->revealUrl('lbc', self::LBC_KEY))->assertOk();
        }

        $this->postJson($this->revealUrl('lbc', self::LBC_KEY))
            ->assertStatus(429)
            ->assertJsonMissing(['value' => self::LBC_VALUE]);

        // Another admin has a budget of their own.
        $this->admin();
        $this->postJson($this->revealUrl('lbc', self::LBC_KEY))->assertOk();
    }

    // ------------------------------------------------------------------
    // Saving
    // ------------------------------------------------------------------

    public function test_saving_with_the_field_untouched_keeps_the_value(): void
    {
        $this->admin();
        $this->store(self::LBC_KEY, self::LBC_VALUE);

        // Resting, the control posts nothing for the field.
        $this->post($this->url('/lbc'), ['settings' => ['lbc_mode' => 'test']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(self::LBC_VALUE, $this->stored(self::LBC_KEY));

        // Opened for editing and left blank is still not a removal.
        $this->post($this->url('/lbc'), ['settings' => [self::LBC_KEY => '   ']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(self::LBC_VALUE, $this->stored(self::LBC_KEY));
    }

    public function test_editing_replaces_the_value_encrypted(): void
    {
        $this->admin();
        $this->store(self::LBC_KEY, self::LBC_VALUE);

        $this->post($this->url('/lbc'), ['settings' => [self::LBC_KEY => 'demo-replacement-key-9d0e']])
            ->assertRedirect()->assertSessionHasNoErrors();

        $raw = Setting::where('key', self::LBC_KEY)->value('value');
        $this->assertNotSame('demo-replacement-key-9d0e', $raw);
        $this->assertSame('demo-replacement-key-9d0e', $this->stored(self::LBC_KEY));
    }

    public function test_remove_clears_only_the_named_secret(): void
    {
        $this->admin();
        $this->store(self::LBC_KEY, self::LBC_VALUE);
        $this->store('lbc_test_track_key', 'demo-track-key-0002');

        $this->post($this->url('/lbc'), ['remove_secrets' => [self::LBC_KEY]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($this->stored(self::LBC_KEY));
        $this->assertSame('demo-track-key-0002', $this->stored('lbc_test_track_key'));

        // And the page then offers a plain empty input for it.
        $this->get($this->url('/lbc'))->assertOk()->assertDontSee('demo-track-key-0002');
    }

    public function test_a_typed_value_wins_over_a_removal_of_the_same_key(): void
    {
        $this->admin();
        $this->store(self::LBC_KEY, self::LBC_VALUE);

        $this->post($this->url('/lbc'), [
            'settings' => [self::LBC_KEY => 'demo-new-key-1111'],
            'remove_secrets' => [self::LBC_KEY],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('demo-new-key-1111', $this->stored(self::LBC_KEY));
    }

    public function test_remove_cannot_name_anything_but_this_couriers_secrets(): void
    {
        $this->admin();
        $this->store('quadx_staging_api_key', 'demo-quadx-api-key-5e21');
        Setting::create(['group' => 'courier', 'key' => 'lbc_client_code', 'value' => 'DEMO-001']);

        foreach (['quadx_staging_api_key', 'lbc_client_code', 'fulfillment_enabled'] as $key) {
            $this->post($this->url('/lbc'), ['remove_secrets' => [$key]])
                ->assertSessionHasErrors('remove_secrets.0');
        }

        $this->assertSame('demo-quadx-api-key-5e21', $this->stored('quadx_staging_api_key'));
        $this->assertSame('DEMO-001', Setting::where('key', 'lbc_client_code')->value('value'));
    }

    public function test_a_value_with_odd_characters_round_trips(): void
    {
        $this->admin();
        $odd = "a+b/c=d&e<f>\"g'h\\i%20{j}[k]|l;m:n`o~p?q#r ñ ü 鍵 🔑 \u{2022}tail";

        $this->post($this->url('/lbc'), ['settings' => [self::LBC_KEY => '  ' . $odd . "\t"]])
            ->assertRedirect()->assertSessionHasNoErrors();

        // Trimmed, otherwise stored exactly as typed.
        $this->assertSame($odd, $this->stored(self::LBC_KEY));

        $this->postJson($this->revealUrl('lbc', self::LBC_KEY))
            ->assertOk()
            ->assertJsonPath('data.value', $odd);

        // And the page escapes the mask's tail rather than printing it raw.
        $this->get($this->url('/lbc'))->assertOk()->assertDontSee('<f>"g', false);
    }

    public function test_length_is_capped_but_format_is_not_checked(): void
    {
        $this->admin();

        $this->post($this->url('/lbc'), ['settings' => [self::LBC_KEY => str_repeat('x', CourierSecrets::MAX_LENGTH + 1)]])
            ->assertSessionHasErrors('settings.' . self::LBC_KEY);
        $this->assertNull($this->stored(self::LBC_KEY));

        $max = str_repeat('k', CourierSecrets::MAX_LENGTH);
        $this->post($this->url('/lbc'), ['settings' => [self::LBC_KEY => $max]])
            ->assertSessionHasNoErrors();
        $this->assertSame($max, $this->stored(self::LBC_KEY));
    }
}
