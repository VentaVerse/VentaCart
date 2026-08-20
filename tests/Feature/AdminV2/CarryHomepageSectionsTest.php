<?php

namespace Tests\Feature\AdminV2;

use App\Models\HomepageSection;
use App\Models\LayoutPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carrying the one-page layout system into the general one.
 *
 * Non-destructive on purpose: homepage_sections is left exactly as it is, so a
 * live store can be compared before and after and rolled back by deleting the
 * placements. The failure to avoid is the one this codebase has already paid
 * for once, where a deploy left correct code sitting on data nobody carried.
 */
class CarryHomepageSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function section(array $attrs = []): HomepageSection
    {
        return HomepageSection::create($attrs + [
            'type' => 'products',
            'title' => 'New Products',
            'config' => ['filter' => 'new', 'limit' => 8],
            'sort_order' => 0,
            'is_enabled' => true,
        ]);
    }

    public function test_it_carries_a_section_with_its_settings_intact(): void
    {
        $this->section();

        $this->artisan('layout:carry-homepage-sections')->assertSuccessful();

        $placement = LayoutPlacement::first();
        $this->assertSame('catalog.products', $placement->widget);
        $this->assertSame('home', $placement->page_type);
        $this->assertSame('content_top', $placement->region);
        $this->assertSame('New Products', $placement->title);
        $this->assertSame(['filter' => 'new', 'limit' => 8], $placement->config);
    }

    public function test_the_old_table_is_left_alone(): void
    {
        $section = $this->section();

        $this->artisan('layout:carry-homepage-sections')->assertSuccessful();

        $this->assertDatabaseHas('homepage_sections', ['id' => $section->id]);
    }

    public function test_running_it_twice_does_not_double_the_layout(): void
    {
        $this->section();

        $this->artisan('layout:carry-homepage-sections')->assertSuccessful();
        $this->artisan('layout:carry-homepage-sections')->assertSuccessful();

        $this->assertSame(1, LayoutPlacement::count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->section();

        $this->artisan('layout:carry-homepage-sections', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, LayoutPlacement::count());
    }

    /** A type with no widget is NAMED, never dropped in silence. */
    public function test_an_unmapped_section_type_is_reported_and_left_in_place(): void
    {
        $this->section(['type' => 'something_bespoke']);

        $this->artisan('layout:carry-homepage-sections')
            ->expectsOutputToContain('something_bespoke')
            ->assertSuccessful();

        $this->assertSame(0, LayoutPlacement::count());
    }

    public function test_disabled_sections_carry_their_disabled_state(): void
    {
        $this->section(['is_enabled' => false]);

        $this->artisan('layout:carry-homepage-sections')->assertSuccessful();

        $this->assertFalse(LayoutPlacement::first()->is_enabled);
    }
}
