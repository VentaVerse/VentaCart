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
            ->assertSeeInOrder(['Store', 'Sales channels', 'Operations', 'Access & system'])
            ->assertSee('Payments')->assertSee('Automations')->assertSee('SEO Engine');
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
