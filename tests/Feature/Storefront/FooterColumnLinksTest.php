<?php

namespace Tests\Feature\Storefront;

use App\Models\FooterColumn;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A links or social footer column keeps its entries under config.links, the
 * key the footer editor saves and every theme reads. Columns written under
 * config.items (by an earlier seeder or "add column") read as links too,
 * so their entries show in the footer and in the editor.
 */
class FooterColumnLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
    }

    public function test_the_seeder_saves_footer_links_under_links(): void
    {
        $this->seed(ThemeSeeder::class);

        $info = FooterColumn::where('title', 'Information')->sole();
        $raw = json_decode($info->getRawOriginal('config'), true);

        $this->assertSame(['About Us', 'Privacy Policy', 'Terms & Conditions'], array_column($raw['links'] ?? [], 'label'));
        $this->assertArrayNotHasKey('items', $raw);

        $social = json_decode(FooterColumn::where('type', 'social')->sole()->getRawOriginal('config'), true);
        $this->assertSame(['facebook', 'instagram'], array_column($social['links'] ?? [], 'platform'));
    }

    public function test_a_column_saved_under_items_shows_its_links_in_the_footer(): void
    {
        FooterColumn::create([
            'title' => 'Information', 'type' => 'links', 'sort_order' => 1, 'is_enabled' => true,
            'config' => ['items' => [['label' => 'About Us', 'url' => '/about'], ['label' => 'Returns', 'url' => '/returns']]],
        ]);

        $this->get('/')->assertOk()
            ->assertSee('href="/about"', false)
            ->assertSee('About Us')
            ->assertSee('Returns');
    }

    public function test_a_column_saved_under_items_reads_as_links(): void
    {
        $links = FooterColumn::create([
            'title' => 'Information', 'type' => 'links', 'sort_order' => 1,
            'config' => ['items' => [['label' => 'About Us', 'url' => '/about']]],
        ])->fresh();
        $icons = FooterColumn::create([
            'title' => 'We accept', 'type' => 'payment_icons', 'sort_order' => 2,
            'config' => ['items' => ['GCash']],
        ])->fresh();

        $this->assertSame([['label' => 'About Us', 'url' => '/about']], $links->config['links']);
        $this->assertArrayNotHasKey('items', $links->config);
        $this->assertSame(['GCash'], $icons->config['icons']);
    }

    public function test_links_saved_under_links_win_over_leftover_items(): void
    {
        $column = FooterColumn::create([
            'title' => 'Information', 'type' => 'links', 'sort_order' => 1,
            'config' => ['links' => [['label' => 'Contact', 'url' => '/contact']], 'items' => [['label' => 'Old', 'url' => '/old']]],
        ])->fresh();

        $this->assertSame([['label' => 'Contact', 'url' => '/contact']], $column->config['links']);
    }

    public function test_a_text_column_is_left_alone(): void
    {
        $column = FooterColumn::create([
            'title' => 'About', 'type' => 'text', 'sort_order' => 1, 'config' => ['content' => 'Hello'],
        ])->fresh();

        $this->assertSame(['content' => 'Hello'], $column->config);
    }
}
