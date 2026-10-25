<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rows and columns inside a region. A row groups
 * placements into columns on the eight-column grid; a placement with no row
 * is an implicit one-column row and renders as it always did.
 */
class LayoutRowsTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $attrs = []): LayoutRow
    {
        return LayoutRow::create($attrs + [
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_bottom',
            'sort_order' => 0, 'columns' => [4, 4], 'is_enabled' => true,
        ]);
    }

    private function place(array $attrs = []): LayoutPlacement
    {
        return LayoutPlacement::create($attrs + [
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_bottom',
            'widget' => 'catalog.brands', 'title' => 'Brands', 'sort_order' => 0, 'is_enabled' => true,
        ]);
    }

    public function test_a_split_row_renders_its_placements_in_their_columns(): void
    {
        $row = $this->row(['columns' => [3, 5]]);
        $this->place(['row_id' => $row->id, 'column_index' => 0, 'title' => 'Left Brands']);
        $this->place(['row_id' => $row->id, 'column_index' => 1, 'title' => 'Right Brands']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('layout-row layout-row--2', $html);
        $this->assertStringContainsString('layout-col--3', $html);
        $this->assertStringContainsString('layout-col--5', $html);
        $this->assertLessThan(strpos($html, 'Right Brands'), strpos($html, 'Left Brands'));
        $this->assertStringNotContainsString('store-row', $html, 'A row inside a region is not the page rail grid.');
    }

    public function test_a_one_column_row_renders_no_grid_around_its_placements(): void
    {
        $row = $this->row(['columns' => [8]]);
        $this->place(['row_id' => $row->id, 'title' => 'Plain Brands']);

        $this->get('/')->assertOk()->assertSee('Plain Brands', false)->assertDontSee('layout-row', false);
    }

    public function test_a_placement_without_a_row_still_renders(): void
    {
        $this->place(['title' => 'Legacy Brands']);

        $this->get('/')->assertOk()->assertSee('Legacy Brands', false)->assertDontSee('layout-row', false);
    }

    public function test_rows_and_loose_placements_keep_the_merchants_order(): void
    {
        $this->place(['title' => 'First Loose', 'sort_order' => 0]);
        $row = $this->row(['sort_order' => 1]);
        $this->place(['row_id' => $row->id, 'title' => 'In The Row', 'sort_order' => 0]);
        $this->place(['title' => 'Last Loose', 'sort_order' => 2]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'In The Row'), strpos($html, 'First Loose'));
        $this->assertLessThan(strpos($html, 'Last Loose'), strpos($html, 'In The Row'));
    }

    public function test_a_row_placed_on_every_page_reaches_every_page(): void
    {
        $row = $this->row(['page_type' => LayoutPlacement::EVERYWHERE, 'region' => 'header_top']);
        $this->place(['row_id' => $row->id, 'page_type' => LayoutPlacement::EVERYWHERE, 'region' => 'header_top', 'title' => 'Everywhere Left']);
        $this->place(['row_id' => $row->id, 'page_type' => LayoutPlacement::EVERYWHERE, 'region' => 'header_top', 'column_index' => 1, 'title' => 'Everywhere Right']);

        foreach (['/', '/cart'] as $url) {
            $this->get($url)->assertOk()->assertSee('Everywhere Left', false)->assertSee('Everywhere Right', false)->assertSee('layout-col--4', false);
        }
    }

    public function test_a_disabled_row_hides_everything_in_it(): void
    {
        $row = $this->row(['is_enabled' => false]);
        $this->place(['row_id' => $row->id, 'title' => 'Hidden Brands']);

        $this->get('/')->assertOk()->assertDontSee('Hidden Brands', false);
    }

    public function test_a_placement_pointing_past_the_last_column_lands_in_the_last_one(): void
    {
        $row = $this->row(['columns' => [4, 4]]);
        $this->place(['row_id' => $row->id, 'column_index' => 7, 'title' => 'Overflow Brands']);

        $units = LayoutRow::forRegion('home', 'content_bottom', 'venta');

        $this->assertCount(1, $units);
        $this->assertSame('Overflow Brands', $units[0]['columns'][1][0]->title);
        $this->assertSame([], $units[0]['columns'][0]);
    }

    public function test_widths_that_do_not_add_up_collapse_to_one_column(): void
    {
        $this->assertSame([8], LayoutRow::normalizeColumns([5, 5]));
        $this->assertSame([8], LayoutRow::normalizeColumns([]));
        $this->assertSame([8], LayoutRow::normalizeColumns([0, 8]));
        $this->assertSame([8], LayoutRow::normalizeColumns([1, 1, 1, 1, 1, 1, 1, 1]));
        $this->assertSame([2, 4, 2], LayoutRow::normalizeColumns(['2', '4', '2']));
    }

    public function test_deleting_a_row_leaves_its_placements_as_loose_ones(): void
    {
        $row = $this->row();
        $placement = $this->place(['row_id' => $row->id, 'title' => 'Survivor']);

        $row->delete();

        $this->assertNull($placement->fresh()->row_id);
        $this->get('/')->assertOk()->assertSee('Survivor', false);
    }
}
