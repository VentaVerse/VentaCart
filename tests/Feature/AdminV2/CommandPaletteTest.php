<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_endpoint_requires_auth(): void
    {
        $this->get(route('admin.palette.search', ['q' => 'x']))->assertRedirect();
    }

    public function test_search_returns_grouped_results(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->getJson(route('admin.palette.search', ['q' => 'zzz-no-match']));
        $response->assertOk()->assertJson(['results' => []]);
    }

    public function test_palette_component_embeds_destinations(): void
    {
        $this->actingAs(User::factory()->create());
        $this->blade('<x-admin.command-palette />')
            ->assertSee('vPalette', false)
            ->assertSee('Dashboard'); // registry destination serialized into the page
    }

    public function test_palette_embeds_settings_destinations(): void
    {
        $this->actingAs(User::factory()->create());
        $this->blade('<x-admin.command-palette />')
            ->assertSee('Settings: Mail')
            ->assertSee('smtp', false);
    }
}
