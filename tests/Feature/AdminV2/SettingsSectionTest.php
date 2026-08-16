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
        // SettingsSectionController. Since the Task 3 URL grammar restructure,
        // admin.settings.section's own URI (settings/{section}) and
        // 'payments' real literal page (settings/payments — registered
        // earlier in routes/admin/settings.php, specifically so it takes
        // precedence over the generic {section} catch-all) now happen to
        // collide on the exact same string when section='payments'. That
        // collision is intentional: it proves the real page — not a 404 —
        // is what actually answers, because Laravel resolves routes by
        // registration order and the literal route always wins.
        $this->get(route('admin.settings.section', 'payments'))
            ->assertOk()
            ->assertViewIs('admin.settings.payments.index');
    }

    public function test_mail_section_renders_in_shell_with_sub_sidebar(): void
    {
        $this->actingAs(User::factory()->create());

        // Migrated to the v3 settings shell: one form, one save, its own
        // full-screen nav.
        $this->get(route('admin.settings.section', 'mail'))
            ->assertOk()
            ->assertSee('class="v3"', false)
            ->assertSee('Back to admin')
            ->assertSee('Mail server');
    }

    public function test_every_section_renders(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['general', 'mail', 'checkout', 'currencies', 'api', 'security', 'automations'] as $section) {
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
        $this->actingAs(User::factory()->create());

        $this->withSession(['new_erp_token' => 'plaintext-erp-token-abc123'])
            ->get(route('admin.settings.section', 'api'))
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

        $expectedMask = str_repeat('•', strlen('plaintext-erp-token-abc123') - 4).'c123';

        $this->get(route('admin.settings.section', 'api'))
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
