<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutColumns;
use App\Models\LayoutPlacement;
use App\Services\ExtensionManager;
use App\Support\PageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What All pages means.
 *
 * Placements made there already appear on every page, so the editor teaches a
 * merchant that the screen means everywhere. Columns and page parts set there
 * were written and never read: PageSetting and the columns component both
 * resolve a concrete page type, which is never the everywhere key. So the one
 * screen made the same promise three times and kept it once.
 */
class LayoutEverywhereFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
        PageSetting::flush();
    }

    private function set(string $pageType, array $attributes): void
    {
        LayoutColumns::updateOrCreate(
            ['theme' => LayoutPlacement::scopeTheme(), 'page_type' => $pageType],
            $attributes
        );
        PageSetting::flush();
    }

    public function test_a_part_hidden_everywhere_is_hidden_on_a_page_that_never_answered(): void
    {
        $this->set('*', ['settings' => ['show_footer' => false]]);

        $this->assertFalse(LayoutColumns::forPage('product')['settings']['show_footer']);
    }

    public function test_a_page_that_answered_for_itself_keeps_its_own_answer(): void
    {
        $this->set('*', ['settings' => ['show_footer' => false]]);
        $this->set('product', ['settings' => ['show_footer' => true]]);

        $this->assertTrue(LayoutColumns::forPage('product')['settings']['show_footer']);
    }

    public function test_two_different_questions_keep_two_different_answers(): void
    {
        $this->set('*', ['settings' => ['show_footer' => false]]);
        $this->set('product', ['settings' => ['show_reviews' => false]]);

        $settings = LayoutColumns::forPage('product')['settings'];

        $this->assertFalse($settings['show_footer'], 'the everywhere answer must survive');
        $this->assertFalse($settings['show_reviews'], 'the page answer must survive');
    }

    public function test_columns_set_everywhere_reach_a_page_that_has_none(): void
    {
        $this->set('*', ['left_width' => 2, 'right_width' => 0]);

        $cols = LayoutColumns::forPage('category');

        $this->assertSame(2, $cols['left']);
        $this->assertSame(6, $cols['content']);
    }

    public function test_a_page_with_its_own_columns_ignores_the_everywhere_row(): void
    {
        $this->set('*', ['left_width' => 2, 'right_width' => 0]);
        $this->set('category', ['left_width' => 0, 'right_width' => 3]);

        $cols = LayoutColumns::forPage('category');

        $this->assertSame(0, $cols['left']);
        $this->assertSame(3, $cols['right']);
    }

    public function test_the_everywhere_row_does_not_look_up_at_itself(): void
    {
        $this->set('*', ['left_width' => 2, 'settings' => ['show_footer' => false]]);

        $cols = LayoutColumns::forPage('*');

        $this->assertSame(2, $cols['left']);
        $this->assertFalse($cols['settings']['show_footer']);
    }

    public function test_a_clamped_everywhere_row_cannot_render_a_page_with_no_content(): void
    {
        $this->set('*', ['left_width' => 7, 'right_width' => 7]);

        $cols = LayoutColumns::forPage('product');

        $this->assertSame(LayoutColumns::MIN_CONTENT, $cols['content']);
    }
}
