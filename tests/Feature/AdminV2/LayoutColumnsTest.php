<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutColumns;
use App\Models\LayoutPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Side columns, and the ratio as a decision rather than a consequence.
 *
 * Journal and OpenCart compute content width from which sidebars exist:
 * col-sm-6 with both, col-sm-9 with one, col-sm-12 with none. A merchant who
 * wants a narrow filter rail beside a wide catalogue cannot express it, because
 * the template already decided. Here each side is set in eighths and the
 * content takes the remainder.
 */
class LayoutColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function rail(string $pageType, string $region = 'column_left'): void
    {
        LayoutPlacement::create([
            'page_type' => $pageType, 'region' => $region,
            'widget' => 'catalog.brands', 'title' => 'Shop by Brand',
            'sort_order' => 0, 'is_enabled' => true,
        ]);
    }

    public function test_a_page_with_no_columns_gets_the_whole_row(): void
    {
        $this->assertSame(['left' => 0, 'right' => 0, 'content' => 8],
            collect(LayoutColumns::forPage('home'))->except('settings')->all());
    }

    public function test_the_content_takes_whatever_the_sides_leave(): void
    {
        LayoutColumns::create(['page_type' => 'category', 'left_width' => 2, 'right_width' => 1]);

        $this->assertSame(['left' => 2, 'right' => 1, 'content' => 5],
            collect(LayoutColumns::forPage('category'))->except('settings')->all());
    }

    /** A stored row must never be able to render a content column of zero. */
    public function test_absurd_widths_are_clamped_rather_than_trusted(): void
    {
        LayoutColumns::create(['page_type' => 'product', 'left_width' => 7, 'right_width' => 7]);

        $cols = LayoutColumns::forPage('product');

        $this->assertGreaterThanOrEqual(LayoutColumns::MIN_CONTENT, $cols['content']);
        $this->assertSame(8, $cols['left'] + $cols['right'] + $cols['content']);
    }

    /* ---------------- rendering ---------------- */

    public function test_a_page_without_columns_renders_no_grid_at_all(): void
    {
        $this->get('/')->assertOk()->assertDontSee('store-row', false);
    }

    /** An empty rail holding the page open is worse than no rail. */
    public function test_a_width_with_nothing_in_it_draws_no_column(): void
    {
        LayoutColumns::create(['page_type' => 'cart', 'left_width' => 2, 'right_width' => 0]);

        $this->get('/cart')->assertOk()->assertDontSee('store-row', false);
    }

    public function test_a_rail_renders_beside_the_content_at_its_ratio(): void
    {
        LayoutColumns::create(['page_type' => 'cart', 'left_width' => 2, 'right_width' => 0]);
        $this->rail('cart');

        $this->get('/cart')
            ->assertOk()
            ->assertSee('store-row', false)
            ->assertSee('store-col--2', false)
            ->assertSee('store-col--6', false)
            ->assertSee('Shop by Brand', false);
    }

    public function test_both_rails_shrink_the_content_together(): void
    {
        LayoutColumns::create(['page_type' => 'cart', 'left_width' => 2, 'right_width' => 2]);
        $this->rail('cart', 'column_left');
        $this->rail('cart', 'column_right');

        $this->get('/cart')->assertOk()->assertSee('store-col--4', false);
    }

    /** The columns belong to that page type, like every other placement. */
    public function test_one_pages_rail_does_not_appear_on_another(): void
    {
        LayoutColumns::create(['page_type' => 'cart', 'left_width' => 2, 'right_width' => 0]);
        $this->rail('cart');

        $this->get('/')->assertOk()->assertDontSee('Shop by Brand', false);
    }
}
