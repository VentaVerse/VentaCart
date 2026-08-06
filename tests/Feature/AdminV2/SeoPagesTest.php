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

    public function test_review_queue_shows_diff_and_bulk_bar(): void
    {
        $this->actingAs(User::factory()->create());
        \App\Models\SeoSuggestion::create([
            'entity_type' => 'product', 'entity_id' => 1,
            'current_meta_title' => 'Old Title', 'suggested_meta_title' => 'New Better Title',
            'status' => 'pending', 'generated_at' => now(),
        ]);

        $this->get(route('admin.extensions.seo.review'))
            ->assertOk()->assertSee('Old Title')->assertSee('New Better Title')
            ->assertSee('bulk', false);
    }
}
