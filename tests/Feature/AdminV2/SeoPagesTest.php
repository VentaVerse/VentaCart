<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SEO is an extension now (wave 1's close); its pages and models
        // exist only while installed and enabled.
        app(\App\Services\ExtensionManager::class)->install('seo');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_dashboard_renders_new_shell_with_grouped_nav_and_cron_card(): void
    {
        $this->actingAs(User::factory()->create());

        // Migrated to the v3 space shell (the dual menu): rail + SEO nav.
        $this->get(route('admin.extensions.seo.dashboard'))
            ->assertOk()->assertSee('v3-embed', false)
            ->assertSeeInOrder(['Work', 'Content', 'Traffic', 'Setup'])
            ->assertSee('Automation');
    }

    public function test_review_queue_shows_diff_and_bulk_bar(): void
    {
        $this->actingAs(User::factory()->create());
        \Extensions\Seo\Models\SeoSuggestion::create([
            'entity_type' => 'product', 'entity_id' => 1,
            'current_meta_title' => 'Old Title', 'suggested_meta_title' => 'New Better Title',
            'status' => 'pending', 'generated_at' => now(),
        ]);

        $this->get(route('admin.extensions.seo.review'))
            ->assertOk()->assertSee('Old Title')->assertSee('New Better Title')
            ->assertSee('bulk', false);
    }

    public function test_all_seo_pages_render_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());
        $routes = [
            route('admin.extensions.seo.review'),
            route('admin.extensions.seo.slugs'),
            route('admin.extensions.seo.search-console'),
            route('admin.extensions.seo.redirects.index'),
            route('admin.extensions.seo.not-found.index'),
            route('admin.extensions.seo.strategy'),
            route('admin.extensions.seo.settings'),
            route('admin.extensions.seo.entities', 'products'),
        ];
        foreach ($routes as $url) {
            $this->get($url)->assertOk()->assertSee('v3-embed', false);
        }
    }
}
