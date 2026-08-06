<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_new_shell_with_grouped_nav_and_cron_card(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.extensions.seo.dashboard'))
            ->assertOk()->assertSee('v-admin', false)
            ->assertSeeInOrder(['Work', 'Content', 'Traffic', 'Setup'])
            ->assertSee('Automation');
    }
}
