<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_page_renders_cards_with_status(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('v-admin', false)   // new shell
            ->assertSee('Stock Alerts')->assertSee('Abandoned Carts')->assertSee('SEO')
            ->assertSee('Configure');
    }
}
