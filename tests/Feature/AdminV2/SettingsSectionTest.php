<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_section_404s(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.settings.section', 'bogus'))->assertNotFound();
    }

    public function test_external_key_is_not_a_section(): void
    {
        $this->actingAs(User::factory()->create());

        // 'payments' exists in the settings-nav registry but points at an
        // external page (PaymentSettingController), not
        // Wave 0 repin: payments left Settings for the Extensions area, so
        // the literal registered before the {section} catch-all is now a
        // 301 to the category's new home — still proving the literal wins
        // over the wildcard, which is the property under test.
        $this->get(route('admin.settings.section', 'payments'))
            ->assertStatus(301)
            ->assertRedirect(route('admin.payment-settings.index'));
    }

    public function test_mail_section_renders_in_shell_with_sub_sidebar(): void
    {
        $this->actingAs(User::factory()->create());

        // Migrated to the v3 settings shell: one form, one save, its own
        // full-screen nav.
        // One-chrome repin: the space's own "Back to admin" band is gone;
        // the classic shell's sidebar is the way back, and the settings
        // menu rides beside it (the context nav marks the page).
        $this->get(route('admin.settings.section', 'mail'))
            ->assertOk()
            ->assertSee('v3-embed', false)
            ->assertSee('v3-settings__main', false)
            ->assertSee('Mail server');
    }

    /**
     * Owner feature (2026-08-17): two purpose-named checkout messages, each
     * with its own toggle and admin-editable label, configured on the
     * Checkout tab. Core owns the fields; fulfillment reads courier_comment.
     */
    public function test_checkout_section_offers_the_two_order_message_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.section', 'checkout'))
            ->assertOk()
            ->assertSee('Order messages')
            ->assertSee('Message to Store')
            ->assertSee('Message to Courier')
            ->assertSee('name="groups[checkout][comment_store_enabled]"', false)
            ->assertSee('name="groups[checkout][comment_store_label]"', false)
            ->assertSee('name="groups[checkout][comment_courier_enabled]"', false)
            ->assertSee('name="groups[checkout][comment_courier_label]"', false);
    }

    public function test_every_section_renders(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['general', 'mail', 'checkout', 'currencies', 'security', 'automations'] as $section) {
            $this->get(route('admin.settings.section', $section))->assertOk();
        }
    }

    public function test_mail_form_saves_through_shared_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('admin.settings.update'), [
            'group' => 'mail',
            '_redirect' => route('admin.settings.section', 'mail'),
            'settings' => ['host' => 'smtp.example.test', 'port' => '587'],
        ])->assertRedirect(route('admin.settings.section', 'mail'));

        $this->assertDatabaseHas('settings', ['group' => 'mail', 'key' => 'host', 'value' => 'smtp.example.test']);
    }

    public function test_old_tabbed_view_is_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/admin/settings/index.blade.php'));
    }

    public function test_api_section_renders_new_erp_token_flash_with_copy_control(): void
    {
        // The whole API section is ERP Sync configuration, so it lives at
        // the extension's own page now (owner ruling: module config never
        // in Settings); settings/api 301s there.
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.section', 'api'))->assertStatus(301);

        $this->withSession(['new_erp_token' => 'plaintext-erp-token-abc123'])
            ->get(route('admin.erp-sync.settings'))
            ->assertOk()
            ->assertSee('New token generated', false)
            ->assertSee('plaintext-erp-token-abc123', false)
            ->assertSee('Copy', false);
    }

    public function test_api_section_masks_plaintext_erp_token_on_decrypt_failure(): void
    {
        $this->actingAs(User::factory()->create());

        \App\Models\Setting::updateOrCreate(
            ['group' => 'api', 'key' => 'erp_token'],
            ['value' => 'plaintext-erp-token-abc123']
        );

        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $expectedMask = str_repeat('•', strlen('plaintext-erp-token-abc123') - 4).'c123';

        $this->get(route('admin.erp-sync.settings'))
            ->assertOk()
            ->assertSee($expectedMask, false); // masked fallback keeps the last 4 chars, not a bare bullet placeholder
    }

    public function test_mail_section_driver_options_match_supported_set(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('admin.settings.section', 'mail'));

        $response->assertOk();
        $response->assertSee('<option value="smtp"', false);
        $response->assertSee('<option value="mail"', false);
        $response->assertDontSee('<option value="sendmail"', false);
        $response->assertDontSee('<option value="log"', false);
    }
}
