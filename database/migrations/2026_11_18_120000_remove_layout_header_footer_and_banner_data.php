<?php

use App\Services\ThemeService;
use App\Support\InstallState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

/**
 * Removes the data of three storefront features VentaCart no longer has:
 * the Layout editor (rows, placements, page columns, drafts, saved
 * templates and style presets, and the older homepage sections), the
 * Header & footer screen (footer columns, the announcement and top bar
 * settings) and the Banners extension (banner groups and slides). Themes
 * now draw their own pages and keep what they edit in their own tables;
 * menus, products and every other piece of store data are not touched.
 *
 * On a store that has run before, every row removed here is first written
 * to storage/app/backups/storefront-export-{time}.json, and the migration
 * refuses to run while the store has no installed live theme, so a failed
 * theme switch keeps everything for a retry. A database dump taken before
 * the upgrade remains the real way back: down() puts back only the empty
 * tables, so the migrations before this one can still be rolled back.
 */
return new class extends Migration
{
    private const TABLES = [
        'layout_placements', 'layout_rows', 'layout_columns', 'layout_drafts',
        'layout_templates', 'layout_style_presets', 'homepage_sections',
        'footer_columns', 'banners', 'banner_groups',
    ];

    /** Registry rows of packages whose folders are gone. */
    private const PACKAGES = ['banners', 'theme-default'];

    /** The Banners extension's own migrations, which ran from its folder. */
    private const PACKAGE_MIGRATIONS = [
        '2026_01_01_000001_create_banners_table',
        '2026_03_30_100001_create_banner_groups_table',
        '2026_03_30_100002_move_slide_dimensions_to_banner_groups',
    ];

    private const PERMISSIONS = ['layout.view', 'layout.edit'];

    /** The shipped migrations that built the removed core tables, in order. */
    private const SCHEMA = [
        '2026_03_06_200001_create_homepage_sections_table',
        '2026_03_06_200003_create_footer_columns_table',
        '2026_08_20_120000_create_layout_placements_table',
        '2026_08_21_090000_create_layout_columns_table',
        '2026_08_21_140000_scope_layout_to_theme',
        '2026_08_31_100000_create_layout_rows_table',
        '2026_08_31_110000_create_layout_style_presets_table',
        '2026_08_31_120000_create_layout_templates_table',
        '2026_09_01_100000_add_style_to_layout_placements',
        '2026_09_01_110000_add_responsive_to_layout_rows',
        '2026_09_01_120000_create_layout_drafts_table',
        '2026_11_14_130000_point_layout_theme_default_at_the_default_theme',
    ];

    public function up(): void
    {
        $fresh = InstallState::isFreshInstall();

        if (! $fresh) {
            $this->refuseWithoutALiveTheme();
            $this->export();
        }

        // Children before the tables they point at.
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        DB::table('settings')->where('group', 'header')
            ->whereIn('key', ['announcement_enabled', 'announcement_text', 'announcement_link', 'topbar_links'])->delete();
        DB::table('settings')->whereIn('id', $this->defaultThemeSettings()->pluck('id'))->delete();

        if (Schema::hasTable('extensions')) {
            DB::table('extensions')->whereIn('name', self::PACKAGES)->delete();
        }
        DB::table('migrations')->whereIn('migration', self::PACKAGE_MIGRATIONS)->delete();

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('slug', self::PERMISSIONS)->pluck('id');
            if ($ids->isNotEmpty()) {
                if (Schema::hasTable('role_permission')) {
                    DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
                }
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }

        foreach (['theme_settings', 'extensions.registry', 'theme_footer_columns', 'theme_homepage_sections', 'theme_nav_items', 'nav_subcategories'] as $key) {
            Cache::forget($key);
        }
    }

    /**
     * The empty tables only, built by the migrations that first made them.
     * The rows are gone; the export and the dump are the way back.
     */
    public function down(): void
    {
        if (Schema::hasTable('layout_rows') || Schema::hasTable('footer_columns')) {
            return;
        }

        foreach (self::SCHEMA as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
    }

    /** The removed default theme's settings, kept as theme_settings.default_*. */
    private function defaultThemeSettings(): \Illuminate\Support\Collection
    {
        return DB::table('settings')->where('group', 'theme_settings')->get()
            ->filter(fn ($row) => str_starts_with((string) $row->key, 'default_'))->values();
    }

    private function refuseWithoutALiveTheme(): void
    {
        $live = (string) DB::table('settings')->where('group', 'identity')->where('key', 'active_theme')->value('value');
        $installed = $live !== ''
            && is_file(ThemeService::root($live . '/theme.json'))
            && DB::table('extensions')->where('name', ThemeService::registryName($live))->where('enabled', true)->exists();

        if (! $installed) {
            throw new \RuntimeException(
                'The storefront has no installed live theme, so the old layout, header and footer, and banner data was kept. '
                . 'Install a theme and make it live (Themes), then run the migrations again.'
            );
        }
    }

    private function export(): void
    {
        $export = ['exported_at' => now()->toIso8601String(), 'tables' => [], 'settings' => []];
        $any = false;

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
                $export['tables'][$table] = $rows;
                $any = $any || $rows !== [];
            }
        }

        $settings = DB::table('settings')->where('group', 'header')->get()
            ->concat($this->defaultThemeSettings())
            ->map(fn ($row) => (array) $row)->values()->all();
        $export['settings'] = $settings;
        $any = $any || $settings !== [];

        if (! $any) {
            return;
        }

        $path = storage_path('app/backups/storefront-export-' . now()->format('Y-m-d-His') . '.json');
        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        File::ensureDirectoryExists(dirname($path));
        if ($json === false || File::put($path, $json) === false) {
            throw new \RuntimeException('The old storefront data could not be exported, so nothing was removed. Check that storage/app is writable.');
        }

        Log::info('The old layout, header and footer, and banner data was exported before removal.', ['path' => $path]);
    }
};
