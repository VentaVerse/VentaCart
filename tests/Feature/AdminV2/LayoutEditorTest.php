<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutColumns;
use App\Models\LayoutPlacement;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The layout editor: what renders where, on which pages.
 *
 * Drawn as the page rather than as a list of positions, because a merchant
 * reasoning about where a widget goes is reasoning spatially, and a vertical
 * list makes them translate. The translation is where mistakes come from.
 */
class LayoutEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    private function save(array $payload)
    {
        return $this->post(route('admin.theme.layout.update'), ['payload' => json_encode($payload)]);
    }

    public function test_it_renders_every_region_the_theme_declares(): void
    {
        $this->get(route('admin.theme.layout', ['page' => 'home']))
            ->assertOk()
            ->assertSee('header_top', false)
            ->assertSee('content_top', false)
            ->assertSee('column_left', false)
            ->assertSee('column_right', false)
            ->assertSee('content_bottom', false)
            ->assertSee('footer_top', false);
    }

    public function test_it_places_a_widget_and_remembers_the_columns(): void
    {
        $this->save([
            'page_type' => 'cart',
            'columns' => ['left' => 2, 'right' => 0],
            'placements' => [
                ['id' => null, 'region' => 'column_left', 'widget' => 'catalog.products', 'title' => 'Popular', 'enabled' => true, 'config' => []],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', [
            'page_type' => 'cart', 'region' => 'column_left', 'widget' => 'catalog.products', 'title' => 'Popular',
        ]);
        $this->assertSame(['left' => 2, 'right' => 0, 'content' => 6],
            collect(LayoutColumns::forPage('cart'))->except('settings')->all());
    }

    /** Order on screen is order in the payload, not whatever the ids were. */
    public function test_the_order_sent_is_the_order_stored(): void
    {
        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'placements' => [
                ['id' => null, 'region' => 'content_top', 'widget' => 'catalog.brands', 'title' => 'B', 'enabled' => true],
                ['id' => null, 'region' => 'content_top', 'widget' => 'catalog.products', 'title' => 'A', 'enabled' => true],
            ],
        ])->assertRedirect();

        $this->assertSame(['B', 'A'],
            LayoutPlacement::where('page_type', 'home')->orderBy('sort_order')->pluck('title')->all());
    }

    /** Whatever the editor did not send back was removed on screen. */
    public function test_a_widget_removed_on_screen_is_removed_in_the_database(): void
    {
        $keep = LayoutPlacement::create(['page_type' => 'home', 'region' => 'content_top', 'widget' => 'catalog.brands', 'title' => 'Keep', 'sort_order' => 0, 'is_enabled' => true]);
        LayoutPlacement::create(['page_type' => 'home', 'region' => 'content_top', 'widget' => 'catalog.products', 'title' => 'Drop', 'sort_order' => 1, 'is_enabled' => true]);

        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'placements' => [
                ['id' => $keep->id, 'region' => 'content_top', 'widget' => 'catalog.brands', 'title' => 'Keep', 'enabled' => true],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['title' => 'Keep']);
        $this->assertDatabaseMissing('layout_placements', ['title' => 'Drop']);
    }

    /**
     * Editing one page must not disturb another, including the '*' set every
     * page inherits.
     */
    public function test_saving_one_page_leaves_every_other_page_alone(): void
    {
        LayoutPlacement::create(['page_type' => '*', 'region' => 'header_top', 'widget' => 'content.text', 'title' => 'Sitewide', 'sort_order' => 0, 'is_enabled' => true]);
        LayoutPlacement::create(['page_type' => 'product', 'region' => 'content_top', 'widget' => 'catalog.brands', 'title' => 'Product only', 'sort_order' => 0, 'is_enabled' => true]);

        $this->save(['page_type' => 'cart', 'columns' => ['left' => 0, 'right' => 0], 'placements' => []])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['title' => 'Sitewide']);
        $this->assertDatabaseHas('layout_placements', ['title' => 'Product only']);
    }

    /* ---------------- what the editor may not invent ---------------- */

    public function test_a_region_this_theme_does_not_declare_is_not_written(): void
    {
        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'placements' => [['id' => null, 'region' => 'faceplate', 'widget' => 'catalog.brands', 'title' => 'Nope', 'enabled' => true]],
        ])->assertRedirect();

        $this->assertDatabaseMissing('layout_placements', ['region' => 'faceplate']);
    }

    public function test_a_widget_nothing_registers_is_not_written(): void
    {
        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'placements' => [['id' => null, 'region' => 'content_top', 'widget' => 'nobody.home', 'title' => 'Nope', 'enabled' => true]],
        ])->assertRedirect();

        $this->assertDatabaseMissing('layout_placements', ['widget' => 'nobody.home']);
    }

    public function test_an_unknown_page_type_is_refused(): void
    {
        $this->save(['page_type' => 'not-a-page', 'columns' => ['left' => 0, 'right' => 0], 'placements' => []])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_a_malformed_payload_changes_nothing(): void
    {
        LayoutPlacement::create(['page_type' => 'home', 'region' => 'content_top', 'widget' => 'catalog.brands', 'title' => 'Safe', 'sort_order' => 0, 'is_enabled' => true]);

        $this->post(route('admin.theme.layout.update'), ['payload' => 'not json'])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['title' => 'Safe']);
    }

    public function test_it_is_behind_admin_auth(): void
    {
        auth()->logout();
        $this->get(route('admin.theme.layout'))->assertRedirect();
    }
}
