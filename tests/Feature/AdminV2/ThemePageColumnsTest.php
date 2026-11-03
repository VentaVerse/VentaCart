<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutColumns;
use App\Models\LayoutPlacement;
use App\Services\ThemeService;
use App\Support\LayoutHooks;
use App\Support\PageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * A theme that keeps its own storage supplies its pages' side column
 * widths and page settings to core's shared rendering, in place of core's
 * layout_columns table, and its side columns are drawn from the rows it
 * supplies.
 */
class ThemePageColumnsTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    protected function tearDown(): void
    {
        LayoutColumns::flushResolvers();
        LayoutHooks::reset();
        parent::tearDown();
    }

    private function ownStorageSample(): void
    {
        $this->installSampleTheme(activate: true, manifest: ['storage' => 'own']);
    }

    /** A rows answer with one text block, the shape core's region draws. */
    private function unitWith(string $text, string $region): \Illuminate\Support\Collection
    {
        $placement = new LayoutPlacement(['theme' => $this->sampleTheme, 'page_type' => 'home', 'region' => $region, 'widget' => 'content.text', 'config' => ['content' => $text], 'is_enabled' => true]);
        $placement->id = 1;

        return collect([['row' => null, 'columns' => [[$placement]]]]);
    }

    public function test_a_theme_supplies_its_side_columns_and_page_settings(): void
    {
        $this->ownStorageSample();
        // A row core holds for the theme is ignored once the theme answers.
        LayoutColumns::create(['theme' => $this->sampleTheme, 'page_type' => '*', 'left_width' => 3, 'right_width' => 0, 'settings' => ['show_breadcrumbs' => true]]);

        $asked = [];
        LayoutColumns::register($this->sampleTheme, function (?string $page) use (&$asked) {
            $asked[] = $page;

            return ['left' => 2, 'right' => 0, 'settings' => ['show_breadcrumbs' => false]];
        });
        LayoutHooks::units(fn (?string $page, string $region) => $region === 'column_left' ? $this->unitWith('Side words', $region) : collect());

        $this->assertSame(['left' => 2, 'right' => 0, 'content' => 6, 'settings' => ['show_breadcrumbs' => false]], LayoutColumns::forPage('home'));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('store-col--2', $html, 'The theme\'s width is drawn.');
        $this->assertStringContainsString('Side words', $html, 'The column holds the rows the theme supplies.');
        $this->assertContains('home', $asked);
    }

    public function test_page_settings_come_from_the_theme(): void
    {
        $this->ownStorageSample();
        $footer = fn () => str_contains($this->get('/')->assertOk()->getContent(), '<footer');
        $this->assertTrue($footer());

        LayoutColumns::register($this->sampleTheme, fn (?string $page) => $page === 'home' ? ['settings' => ['show_footer' => '0']] : null);
        $this->assertFalse($footer(), "The theme's answer hides the footer on its home page.");
    }

    public function test_widths_are_clamped_and_an_empty_column_is_not_drawn(): void
    {
        $this->ownStorageSample();
        LayoutColumns::register($this->sampleTheme, fn () => ['left' => 99, 'right' => 99]);
        $this->assertSame(['left' => 5, 'right' => 0, 'content' => 3, 'settings' => []], LayoutColumns::forPage('home'));

        LayoutColumns::register($this->sampleTheme, fn () => null);
        $this->assertSame(['left' => 0, 'right' => 0, 'content' => 8, 'settings' => []], LayoutColumns::forPage('home'), 'No answer, no columns.');

        LayoutColumns::register($this->sampleTheme, fn () => ['left' => 2]);
        LayoutHooks::units(fn () => collect());
        $this->assertStringNotContainsString('store-col--2', $this->get('/')->assertOk()->getContent(), 'A width with nothing in it holds no rail.');
    }

    public function test_core_themes_still_read_the_table(): void
    {
        LayoutColumns::register('another-theme', fn () => ['left' => 2]);
        LayoutColumns::create(['theme' => ThemeService::THEME, 'page_type' => '*', 'left_width' => 3, 'right_width' => 0, 'settings' => []]);

        $this->assertSame(3, LayoutColumns::forPage('home', ThemeService::THEME)['left']);
    }
}
