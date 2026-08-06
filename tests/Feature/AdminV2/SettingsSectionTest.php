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
        // 'payments' exists in the registry but points at an external page
        $this->get(route('admin.settings.section', 'payments'))->assertNotFound();
    }

    public function test_mail_section_renders_in_shell_with_sub_sidebar(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.section', 'mail'))
            ->assertOk()
            ->assertSee('v-admin', false)      // new shell
            ->assertSee('All Settings')        // sub-sidebar back link
            ->assertSee('SMTP');
    }
}
