<?php

namespace Tests\Feature\Themes;

use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The upgrade step that removes the Layout, Header & footer and Banners
 * data: it exports every row first, refuses while the store has no live
 * theme, and touches nothing else. A fresh install simply never has the
 * tables.
 */
class StorefrontDataRemovalTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'layout_placements', 'layout_rows', 'layout_columns', 'layout_drafts',
        'layout_templates', 'layout_style_presets', 'homepage_sections',
        'footer_columns', 'banners', 'banner_groups',
    ];

    private string $backups;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backups = storage_path('app/backups');
        $this->beforeApplicationDestroyed(function () {
            foreach (glob($this->backups . '/storefront-export-*.json') ?: [] as $file) {
                if (filemtime($file) >= time() - 600) {
                    @unlink($file);
                }
            }
        });
    }

    private function removal(): void
    {
        (require database_path('migrations/2026_11_18_120000_remove_layout_header_footer_and_banner_data.php'))->up();
    }

    /** A store that ran the features: their tables with a row each, and their settings and rows elsewhere. */
    private function anOlderStore(): void
    {
        DB::table('migrations')->insert(['migration' => '2026_01_01_000000_an_earlier_release', 'batch' => 2]);
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => self::SAMPLE_THEME]);
        foreach (['layout_rows', 'footer_columns', 'banner_groups'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('title')->nullable();
            });
            DB::table($table)->insert(['title' => "Demo {$table}"]);
        }
        Setting::create(['group' => 'header', 'key' => 'announcement_text', 'value' => 'Free delivery']);
        Setting::create(['group' => 'header', 'key' => 'topbar_links', 'value' => '[]']);
        Setting::create(['group' => 'theme_settings', 'key' => 'default_hero', 'value' => '1']);
        Setting::create(['group' => 'theme_settings', 'key' => 'sample_hero', 'value' => '1']);
        foreach (['banners', 'theme-default'] as $name) {
            DB::table('extensions')->insert(['name' => $name, 'version' => '1.0.0', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('migrations')->insert(['migration' => '2026_03_30_100001_create_banner_groups_table', 'batch' => 1]);
        $permission = DB::table('permissions')->insertGetId(['slug' => 'layout.edit', 'name' => 'Edit layout', 'group' => 'layout', 'created_at' => now(), 'updated_at' => now()]);
        $role = DB::table('roles')->insertGetId(['name' => 'Designer', 'slug' => 'designer', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permission')->insert(['role_id' => $role, 'permission_id' => $permission]);
    }

    public function test_a_fresh_install_has_none_of_the_tables(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertFalse(DB::table('permissions')->whereIn('slug', ['layout.view', 'layout.edit'])->exists());
    }

    public function test_an_older_store_is_exported_then_cleared_and_keeps_everything_else(): void
    {
        $this->anOlderStore();
        $before = glob($this->backups . '/storefront-export-*.json') ?: [];

        $this->removal();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertSame([], Setting::where('group', 'header')->pluck('key')->all());
        $this->assertFalse(Setting::where('group', 'theme_settings')->where('key', 'default_hero')->exists());
        $this->assertTrue(Setting::where('group', 'theme_settings')->where('key', 'sample_hero')->exists(), 'Other themes keep their settings.');
        $this->assertSame([], DB::table('extensions')->whereIn('name', ['banners', 'theme-default'])->pluck('name')->all());
        $this->assertTrue(DB::table('extensions')->where('name', 'theme-sample')->exists(), 'The live theme stays installed.');
        $this->assertFalse(DB::table('migrations')->where('migration', '2026_03_30_100001_create_banner_groups_table')->exists());
        $this->assertFalse(DB::table('permissions')->where('slug', 'layout.edit')->exists());
        $this->assertSame(0, DB::table('role_permission')->count());

        $written = array_values(array_diff(glob($this->backups . '/storefront-export-*.json') ?: [], $before));
        $this->assertCount(1, $written);
        $export = json_decode((string) file_get_contents($written[0]), true);
        $this->assertSame('Demo layout_rows', $export['tables']['layout_rows'][0]['title']);
        $this->assertSame('Demo footer_columns', $export['tables']['footer_columns'][0]['title']);
        $this->assertSame('Demo banner_groups', $export['tables']['banner_groups'][0]['title']);
        $this->assertContains('Free delivery', array_column($export['settings'], 'value'));

        // Run again: nothing left to do, nothing breaks.
        $this->removal();
    }

    public function test_a_store_without_a_live_theme_keeps_everything(): void
    {
        $this->anOlderStore();
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'a-removed-theme']);

        try {
            $this->removal();
            $this->fail('The removal ran with no live theme.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no installed live theme', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('layout_rows'));
        $this->assertTrue(Setting::where('group', 'header')->exists());
        $this->assertTrue(DB::table('extensions')->where('name', 'banners')->exists());
    }

    /** Rolling back puts the empty tables back, so earlier migrations can roll back too. */
    public function test_rolling_back_puts_back_only_the_empty_tables(): void
    {
        (require database_path('migrations/2026_11_18_120000_remove_layout_header_footer_and_banner_data.php'))->down();

        foreach (['layout_rows', 'layout_placements', 'footer_columns', 'homepage_sections', 'layout_drafts'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertTrue(Schema::hasColumn('layout_rows', 'responsive'));
    }
}
