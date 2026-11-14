<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The theme that ships with core is now called "default" (folder
 * resources/themes/default). Every place a store recorded its old slug moves
 * with it: the active theme setting, the theme column of the layout tables,
 * and the theme's row in the extensions table. Without this, a store on the
 * default theme would point at a folder that no longer exists, and every
 * store would lose the default theme's layout.
 *
 * Safe on a fresh install (nothing to rename) and safe to run again (only
 * rows still carrying the old slug are touched).
 */
return new class extends Migration
{
    private const FROM = 'venta';

    private const TO = 'default';

    private const LAYOUT_TABLES = [
        'layout_rows',
        'layout_columns',
        'layout_placements',
        'layout_drafts',
        'layout_templates',
        'layout_style_presets',
    ];

    public function up(): void
    {
        $this->rename(self::FROM, self::TO);
    }

    public function down(): void
    {
        $this->rename(self::TO, self::FROM);
    }

    private function rename(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to) {
            if (Schema::hasTable('settings')) {
                DB::table('settings')
                    ->where('group', 'identity')->where('key', 'active_theme')->where('value', $from)
                    ->update(['value' => $to]);
            }

            foreach (self::LAYOUT_TABLES as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'theme')) {
                    DB::table($table)->where('theme', $from)->update(['theme' => $to]);
                }
            }

            if (Schema::hasTable('extensions')) {
                $old = 'theme-' . $from;
                $new = 'theme-' . $to;

                // A row under the new name can only exist if the extension
                // list was rebuilt before this ran; keep that one.
                if (DB::table('extensions')->where('name', $new)->exists()) {
                    DB::table('extensions')->where('name', $old)->delete();
                } else {
                    DB::table('extensions')->where('name', $old)->update(['name' => $new]);
                }
            }
        });

        // Both caches hold the old slug: the theme settings and the
        // extension registry. They rebuild on the next request.
        Cache::forget('theme_settings');
        Cache::forget('extensions.registry');
    }
};
