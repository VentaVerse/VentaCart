<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Layout editor saves rows: their column widths, their order, and the
 * cards in each column. A flat placements payload, the shape before rows
 * existed, is still accepted and becomes one-column rows.
 */
class LayoutRowsEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('theme');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    private function save(array $payload)
    {
        return $this->post(route('admin.layout.update'), ['payload' => json_encode($payload)]);
    }

    public function test_a_split_row_round_trips_with_its_cards_in_their_columns(): void
    {
        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0], 'settings' => [],
            'rows' => [[
                'id' => null, 'region' => 'content_top', 'columns' => [3, 5], 'enabled' => true,
                'placements' => [
                    ['id' => null, 'column' => 0, 'widget' => 'catalog.brands', 'title' => 'Left', 'enabled' => true, 'config' => []],
                    ['id' => null, 'column' => 1, 'widget' => 'catalog.brands', 'title' => 'Right', 'enabled' => true, 'config' => []],
                ],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $row = LayoutRow::sole();
        $this->assertSame([3, 5], $row->columns);
        $this->assertSame('content_top', $row->region);
        $this->assertDatabaseHas('layout_placements', ['title' => 'Left', 'row_id' => $row->id, 'column_index' => 0]);
        $this->assertDatabaseHas('layout_placements', ['title' => 'Right', 'row_id' => $row->id, 'column_index' => 1]);

        $this->get(route('admin.layout', ['page' => 'home']))->assertOk()
            ->assertSee('[3,5]', false)->assertSee('Left', false)->assertSee('Right', false);

        $this->get('/')->assertOk()->assertSee('layout-col--3', false)->assertSee('layout-col--5', false);
    }

    public function test_widths_that_do_not_add_up_are_written_as_one_column(): void
    {
        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => null, 'region' => 'content_top', 'columns' => [5, 5], 'enabled' => true,
                'placements' => [['id' => null, 'column' => 1, 'widget' => 'catalog.brands', 'title' => 'B', 'enabled' => true]]]],
        ])->assertRedirect();

        $this->assertSame([8], LayoutRow::sole()->columns);
        $this->assertDatabaseHas('layout_placements', ['title' => 'B', 'column_index' => 0]);
    }

    public function test_a_row_removed_on_screen_is_removed_and_its_cards_with_it(): void
    {
        $row = LayoutRow::create(['theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top', 'columns' => [8], 'sort_order' => 0]);
        LayoutPlacement::create(['theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top', 'row_id' => $row->id, 'widget' => 'catalog.brands', 'title' => 'Gone', 'sort_order' => 0]);
        $keep = LayoutRow::create(['theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top', 'columns' => [8], 'sort_order' => 1]);
        LayoutPlacement::create(['theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top', 'row_id' => $keep->id, 'widget' => 'catalog.brands', 'title' => 'Stays', 'sort_order' => 1]);
        $stays = LayoutPlacement::where('title', 'Stays')->sole();

        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => $keep->id, 'region' => 'content_top', 'columns' => [8], 'enabled' => true,
                'placements' => [['id' => $stays->id, 'column' => 0, 'widget' => 'catalog.brands', 'title' => 'Stays', 'enabled' => true]]]],
        ])->assertRedirect();

        $this->assertDatabaseMissing('layout_rows', ['id' => $row->id]);
        $this->assertDatabaseMissing('layout_placements', ['title' => 'Gone']);
        $this->assertDatabaseHas('layout_placements', ['title' => 'Stays', 'row_id' => $keep->id]);
    }

    public function test_the_order_of_rows_sent_is_the_order_stored(): void
    {
        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [
                ['id' => null, 'region' => 'content_top', 'columns' => [8], 'enabled' => true, 'placements' => [['id' => null, 'column' => 0, 'widget' => 'catalog.brands', 'title' => 'First', 'enabled' => true]]],
                ['id' => null, 'region' => 'content_top', 'columns' => [4, 4], 'enabled' => false, 'placements' => [['id' => null, 'column' => 1, 'widget' => 'catalog.brands', 'title' => 'Second', 'enabled' => true]]],
            ],
        ])->assertRedirect();

        $rows = LayoutRow::orderBy('sort_order')->get();
        $this->assertSame([[8], [4, 4]], $rows->pluck('columns')->all());
        $this->assertFalse($rows[1]->is_enabled);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('First', $html);
        $this->assertStringNotContainsString('Second', $html, 'A hidden row shows nothing.');
    }

    public function test_a_flat_placements_payload_still_saves_as_one_column_rows(): void
    {
        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'placements' => [
                ['id' => null, 'region' => 'content_top', 'widget' => 'catalog.brands', 'title' => 'Old Shape', 'enabled' => true],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['title' => 'Old Shape', 'column_index' => 0]);
        $this->assertSame([8], LayoutRow::sole()->columns);
        $this->assertNotNull(LayoutPlacement::sole()->row_id);
    }

    public function test_a_row_id_from_another_page_is_not_hijacked(): void
    {
        $other = LayoutRow::create(['theme' => 'venta', 'page_type' => 'cart', 'region' => 'content_top', 'columns' => [4, 4], 'sort_order' => 0]);

        $this->save([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => $other->id, 'region' => 'content_top', 'columns' => [8], 'enabled' => true,
                'placements' => [['id' => null, 'column' => 0, 'widget' => 'catalog.brands', 'title' => 'Home', 'enabled' => true]]]],
        ])->assertRedirect();

        $this->assertSame([4, 4], $other->fresh()->columns, 'The cart page row is untouched.');
        $this->assertSame('cart', $other->fresh()->page_type);
        $this->assertSame(2, LayoutRow::count());
    }

    public function test_the_editor_ships_the_shapes_it_offers(): void
    {
        $this->get(route('admin.layout', ['page' => 'home']))->assertOk()
            ->assertSee('layouts:', false)->assertSee('[2,4,2]', false)->assertSee('Add a row to', false);
    }
}
