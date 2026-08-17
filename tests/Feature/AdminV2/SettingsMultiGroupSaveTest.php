<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The v3 save doctrine's server half: one submit may carry several
 * setting groups, and a secret submitted blank keeps its stored value.
 */
class SettingsMultiGroupSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_save_persists_several_groups(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.settings.update'), [
            'groups' => [
                'checkout' => ['guest_allowed' => '1'],
                'stock' => ['low_stock_threshold' => '7'],
            ],
        ])->assertRedirect();

        $this->assertSame('1', Setting::where('group', 'checkout')->where('key', 'guest_allowed')->value('value'));
        $this->assertSame('7', Setting::where('group', 'stock')->where('key', 'low_stock_threshold')->value('value'));
    }

    public function test_blank_password_keeps_the_stored_value(): void
    {
        $this->actingAs(User::factory()->create());
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'password'], ['value' => 'kept-secret']);

        $this->post(route('admin.settings.update'), [
            'groups' => ['mail' => ['host' => 'smtp.example.test', 'password' => null]],
        ])->assertRedirect();

        $this->assertSame('kept-secret', Setting::where('group', 'mail')->where('key', 'password')->value('value'));
        $this->assertSame('smtp.example.test', Setting::where('group', 'mail')->where('key', 'host')->value('value'));
    }

    public function test_invalid_admin_prefix_is_rejected_before_any_write(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('admin.settings.section', 'security'))
            ->post(route('admin.settings.update'), [
                'groups' => ['security' => ['admin_prefix' => 'Bad Prefix!']],
            ])->assertRedirect(route('admin.settings.section', 'security'));

        $this->assertNull(Setting::where('group', 'security')->where('key', 'admin_prefix')->value('value'));
    }

    public function test_mail_test_endpoint_returns_json_verdict(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('admin.settings.test-mail'), [
            'to' => 'probe@example.test',
            'override' => ['driver' => 'smtp', 'host' => ''],
        ])->assertOk()->assertJson(['ok' => false]);
    }

    public function test_stored_password_never_rides_to_a_changed_transport(): void
    {
        $this->actingAs(User::factory()->create());
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'host'], ['value' => 'smtp.real.test']);
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'port'], ['value' => '587']);
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'username'], ['value' => 'merchant']);
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'password'], ['value' => 'the-secret']);

        // Changed host + blank password: refused before any connection,
        // so the secret cannot be exfiltrated to an attacker-named server.
        $this->postJson(route('admin.settings.test-mail'), [
            'to' => 'probe@example.test',
            'override' => ['driver' => 'smtp', 'host' => 'smtp.evil.test', 'port' => '587', 'username' => 'merchant', 'password' => ''],
        ])->assertOk()->assertJson(['ok' => false])
          ->assertJsonPath('message', 'The server details changed. Type the password to test the new transport.');
    }

    public function test_theme_settings_save_carries_every_tab_in_one_submit(): void
    {
        app(\App\Services\ExtensionManager::class)->install('theme');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.theme.settings.update'), [
            'groups' => [
                'colors' => ['primary' => '#123456'],
                'badges' => ['new_count' => '7'],
                'filters' => ['price_enabled' => '1'],
            ],
        ])->assertRedirect();

        $this->assertSame('#123456', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
        $this->assertSame('7', Setting::where('group', 'badges')->where('key', 'new_count')->value('value'));
        $this->assertSame('1', Setting::where('group', 'filters')->where('key', 'price_enabled')->value('value'));
    }

    public function test_identity_endpoint_only_writes_the_general_group(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.settings.identity.update'), [
            'site_name' => 'Probe Store',
            'groups' => [
                'general' => ['timezone' => 'Asia/Manila'],
                'security' => ['admin_prefix' => 'smuggled'],
            ],
        ])->assertRedirect();

        $this->assertSame('Asia/Manila', Setting::where('group', 'general')->where('key', 'timezone')->value('value'));
        $this->assertNull(Setting::where('group', 'security')->where('key', 'admin_prefix')->value('value'));
    }
}
