<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a save is allowed to destroy.
 *
 * The editor shows two kinds of placement it cannot send back: one whose
 * module is switched off (a "Module off" card) and one in a region this theme
 * no longer declares (the orphans block). Both are rendered with a promise
 * that they are kept. The save swept both away, so switching an extension off
 * for an afternoon and tidying a page destroyed the placement and its config
 * under a success message.
 */
class LayoutSaveSparesStrandedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A widget key nothing registers, which is exactly what a placement looks
     * like when its module is switched off. Named rather than borrowed from a
     * real module, because the registry is process-global static state: an
     * earlier test that installs banners leaves banners.group registered, and
     * a test that quietly depends on registry emptiness passes alone and fails
     * in the suite.
     */
    private const SWITCHED_OFF = 'switched.off.module';

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

    private function place(array $attributes): LayoutPlacement
    {
        return LayoutPlacement::create($attributes + [
            'theme' => LayoutPlacement::scopeTheme(),
            'page_type' => 'home',
            'sort_order' => 0,
            'is_enabled' => true,
            'config' => [],
        ]);
    }

    public function test_a_placement_whose_module_is_off_survives_a_save(): void
    {
        $off = $this->place(['region' => 'content_top', 'widget' => self::SWITCHED_OFF, 'title' => 'Hero']);

        $this->assertFalse(Widgets::has(self::SWITCHED_OFF), 'the stand-in must not be registered or this test proves nothing');

        $this->save([
            'page_type' => 'home',
            'columns' => ['left' => 0, 'right' => 0],
            'placements' => [
                ['id' => null, 'region' => 'content_top', 'widget' => 'catalog.products', 'title' => 'New', 'config' => [], 'enabled' => true],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['id' => $off->id, 'title' => 'Hero']);
    }

    public function test_a_placement_in_a_region_the_theme_dropped_survives_a_save(): void
    {
        $orphan = $this->place(['region' => 'sidebar_legacy', 'widget' => 'catalog.products', 'title' => 'Stranded']);

        $this->save([
            'page_type' => 'home',
            'columns' => ['left' => 0, 'right' => 0],
            'placements' => [
                ['id' => null, 'region' => 'content_top', 'widget' => 'catalog.products', 'title' => 'New', 'config' => [], 'enabled' => true],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['id' => $orphan->id, 'title' => 'Stranded']);
    }

    public function test_the_count_a_merchant_is_told_never_includes_what_they_cannot_see(): void
    {
        $this->place(['region' => 'content_top', 'widget' => self::SWITCHED_OFF, 'title' => 'Hero']);

        $this->save([
            'page_type' => 'home',
            'columns' => ['left' => 0, 'right' => 0],
            'placements' => [
                ['id' => null, 'region' => 'content_top', 'widget' => 'catalog.products', 'title' => 'New', 'config' => [], 'enabled' => true],
            ],
        ])->assertSessionHas('success', '1 widget placed on Home.');
    }

    public function test_emptying_a_page_still_empties_it(): void
    {
        $gone = $this->place(['region' => 'content_top', 'widget' => 'catalog.products', 'title' => 'Doomed']);

        $this->save(['page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0], 'placements' => []])
            ->assertSessionHas('success', 'Home is now empty.');

        $this->assertDatabaseMissing('layout_placements', ['id' => $gone->id]);
    }

    public function test_emptying_a_page_says_what_is_still_standing(): void
    {
        $this->place(['region' => 'content_top', 'widget' => self::SWITCHED_OFF, 'title' => 'Hero']);
        $gone = $this->place(['region' => 'content_top', 'widget' => 'catalog.products', 'title' => 'Doomed']);

        $this->save(['page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0], 'placements' => []])
            ->assertSessionHas('success', 'Home is now empty apart from 1 widget waiting on a module.');

        $this->assertDatabaseMissing('layout_placements', ['id' => $gone->id]);
    }

    /**
     * The old sweep dropped its whereNotIn when nothing was kept, so a save
     * where every row was refused deleted the whole page type and then
     * reported an error about a transaction that had already committed.
     */
    public function test_a_save_where_every_row_is_refused_changes_nothing(): void
    {
        $safe = $this->place(['region' => 'content_top', 'widget' => 'catalog.products', 'title' => 'Keep me']);

        $this->save([
            'page_type' => 'home',
            'columns' => ['left' => 0, 'right' => 0],
            'placements' => [
                ['id' => null, 'region' => 'content_top', 'widget' => 'nothing.registers.this', 'title' => 'Bogus', 'config' => [], 'enabled' => true],
            ],
        ])->assertSessionHas('error');

        $this->assertDatabaseHas('layout_placements', ['id' => $safe->id, 'title' => 'Keep me']);
    }

    public function test_a_save_on_one_page_never_touches_another(): void
    {
        $other = $this->place(['page_type' => 'product', 'region' => 'content_top', 'widget' => self::SWITCHED_OFF, 'title' => 'Elsewhere']);

        $this->save(['page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0], 'placements' => []])
            ->assertRedirect();

        $this->assertDatabaseHas('layout_placements', ['id' => $other->id]);
    }
}
