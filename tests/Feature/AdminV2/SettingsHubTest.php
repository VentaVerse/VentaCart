<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_renders_groups_and_cards(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.index'))
            ->assertOk()
            // v settings area grouping: general is general, a module's
            // settings live with the module (SEO settings moved to the SEO
            // module space, so the hub no longer lists it).
            ->assertSeeInOrder(['Store', 'Commerce', 'Communication', 'System'])
            ->assertSee('Payments')->assertSee('Automations')->assertSee('Notifications');
    }

    public function test_legacy_tab_param_redirects_to_section(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.index', ['tab' => 'mail']))
            ->assertRedirect(route('admin.settings.section', 'mail'));
        $this->get(route('admin.settings.index', ['tab' => 'currency']))
            ->assertRedirect(route('admin.settings.section', 'currencies'));
    }

    public function test_hub_requires_auth(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect();
    }
}
