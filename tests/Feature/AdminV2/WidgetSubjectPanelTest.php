<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\AdminFrame;
use App\Support\Widgets;
use Extensions\Banners\Models\BannerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing a widget's own record from the layout editor.
 *
 * The seam under test is ownership. The layout editor knows a widget points
 * at SOMETHING and can list the choices; it never learns what a banner group
 * is, and the banners module never learns it is being shown in a panel.
 */
class WidgetSubjectPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->install('banners');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    public function test_a_widget_hands_over_rows_the_editor_can_render(): void
    {
        $group = BannerGroup::create(['name' => 'Homepage hero', 'slug' => 'homepage-hero', 'is_active' => true]);

        $field = collect(Widgets::schema('banners.group'))->firstWhere('name', 'banner_group');

        $this->assertIsArray($field['browse']);
        $row = collect($field['browse'])->firstWhere('value', 'homepage-hero');

        $this->assertSame('Homepage hero', $row['label']);
        $this->assertSame('0 slides', $row['meta']);
        $this->assertStringContainsString('/slides', $row['url']);
    }

    public function test_the_editor_stamps_the_frame_flag_so_a_module_never_has_to(): void
    {
        BannerGroup::create(['name' => 'Hero', 'slug' => 'hero', 'is_active' => true]);

        $field = collect(Widgets::schema('banners.group'))->firstWhere('name', 'banner_group');

        $this->assertStringContainsString(AdminFrame::FLAG . '=1', $field['browse'][0]['url']);
    }

    public function test_a_widget_without_a_subject_offers_no_rows(): void
    {
        $field = collect(Widgets::schema('catalog.products'))->firstWhere('name', 'filter');

        $this->assertArrayNotHasKey('browse', $field);
    }

    public function test_a_module_that_cannot_list_its_records_degrades_instead_of_falling_over(): void
    {
        Widgets::register('test.broken', [
            'title' => 'Broken',
            'schema' => [
                'thing' => [
                    'type' => 'select',
                    'browse' => fn () => throw new \RuntimeException('table is gone'),
                ],
            ],
        ]);

        $field = collect(Widgets::schema('test.broken'))->firstWhere('name', 'thing');

        $this->assertSame([], $field['browse']);
        $this->assertTrue($field['unavailable']);

        Widgets::forget('test.broken');
    }

    public function test_a_row_missing_its_value_is_dropped_rather_than_rendered_broken(): void
    {
        Widgets::register('test.partial', [
            'title' => 'Partial',
            'schema' => [
                'thing' => [
                    'type' => 'select',
                    'browse' => fn () => [
                        ['label' => 'No value here'],
                        ['value' => 'ok', 'label' => 'Fine', 'thumbs' => ['', null, '/img.jpg']],
                    ],
                ],
            ],
        ]);

        $field = collect(Widgets::schema('test.partial'))->firstWhere('name', 'thing');

        $this->assertCount(1, $field['browse']);
        $this->assertSame('ok', $field['browse'][0]['value']);
        $this->assertSame(['/img.jpg'], $field['browse'][0]['thumbs']);
        $this->assertNull($field['browse'][0]['url']);

        Widgets::forget('test.partial');
    }

    public function test_the_layout_editor_ships_the_rows_to_the_page(): void
    {
        BannerGroup::create(['name' => 'Homepage hero', 'slug' => 'homepage-hero', 'is_active' => true]);

        $this->get(route('admin.theme.layout', ['page' => 'home']))
            ->assertOk()
            ->assertSee('browse', false)
            ->assertSee('Homepage hero', false);
    }

    public function test_a_framed_module_page_drops_the_furniture_but_stays_the_admin(): void
    {
        $group = BannerGroup::create(['name' => 'Hero', 'slug' => 'hero', 'is_active' => true]);

        $plain = $this->get(route('admin.banners.slides', $group))->assertOk();
        $plain->assertSee('v-slab', false);
        $plain->assertSee('v-topbar', false);

        $framed = $this->get(route('admin.banners.slides', $group) . '?embed=1')->assertOk();
        $framed->assertSee('v-framed', false);
        $framed->assertDontSee('v-topbar', false);
        $framed->assertDontSee('id="v-slab"', false);
        // Still the admin: the page's own content and its save doctrine.
        $framed->assertSee('Hero', false);
        $framed->assertSee('venta-frame', false);
    }

    public function test_an_unframed_page_never_runs_the_bridge(): void
    {
        $group = BannerGroup::create(['name' => 'Hero', 'slug' => 'hero', 'is_active' => true]);

        $this->get(route('admin.banners.slides', $group))
            ->assertOk()
            ->assertDontSee('venta-frame', false);
    }
}
