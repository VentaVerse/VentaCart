<?php

namespace Tests\Feature;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A store installed before the core theme was renamed still records the old
 * slug. The rename migration must move all of it, leave other themes alone,
 * and be safe to run a second time.
 */
class DefaultThemeRenameMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_14_120000_rename_default_theme_slug.php');
    }

    public function test_an_older_store_moves_to_the_new_slug_and_other_themes_stay(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'venta']);
        $row = LayoutRow::create(['theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top', 'columns' => [12], 'sort_order' => 0]);
        LayoutPlacement::create(['theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top', 'row_id' => $row->id, 'widget' => 'catalog.brands', 'title' => 'Brands', 'sort_order' => 0]);
        $other = LayoutRow::create(['theme' => 'sample', 'page_type' => 'home', 'region' => 'content_top', 'columns' => [12], 'sort_order' => 0]);
        DB::table('extensions')->where('name', 'theme-default')->delete();
        DB::table('extensions')->insert(['name' => 'theme-venta', 'version' => '2.0', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('default', Setting::where('group', 'identity')->where('key', 'active_theme')->value('value'));
        $this->assertSame('default', $row->fresh()->theme);
        $this->assertSame(0, LayoutPlacement::where('theme', 'venta')->count());
        $this->assertSame(1, LayoutPlacement::where('theme', 'default')->count());
        $this->assertSame('sample', $other->fresh()->theme);
        $this->assertFalse(DB::table('extensions')->where('name', 'theme-venta')->exists());
        $this->assertTrue(DB::table('extensions')->where('name', 'theme-default')->exists());
    }

    public function test_a_store_on_another_theme_keeps_its_choice(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'sample']);

        $this->migration()->up();

        $this->assertSame('sample', Setting::where('group', 'identity')->where('key', 'active_theme')->value('value'));
    }

    public function test_a_layout_row_written_without_a_theme_lands_on_the_default_theme(): void
    {
        $id = DB::table('layout_placements')->insertGetId([
            'page_type' => 'home', 'region' => 'content_top', 'widget' => 'catalog.brands',
            'title' => 'Brands', 'sort_order' => 0, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('default', DB::table('layout_placements')->where('id', $id)->value('theme'));
    }
}
