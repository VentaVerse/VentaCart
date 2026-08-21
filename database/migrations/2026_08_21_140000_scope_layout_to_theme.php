<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A layout belongs to the theme it was built for.
 *
 * Placements were global, so switching theme left rows pointing at regions the
 * new theme has never declared, and the editor had to warn about orphans.
 * Scoping to the theme removes the situation instead of managing it: switch and
 * you get that theme's layout, switch back and yours is exactly as you left it.
 *
 * The evidence for this is in the merchant's own legacy store, where
 * oc_layout_module still holds "journal2_static_banners.72" in a shop that runs
 * Journal 3: placements from a theme two versions dead, pointing at content
 * that no longer exists, with nothing to report them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $default = \App\Services\ThemeService::THEME;

        Schema::table('layout_placements', function (Blueprint $table) use ($default) {
            $table->string('theme', 64)->default($default)->after('id')->index();
        });

        Schema::table('layout_columns', function (Blueprint $table) use ($default) {
            $table->string('theme', 64)->default($default)->after('id');
            // The page's own editable parts: what the theme declares can be
            // hidden or changed on a hardcoded page. Journal keeps the same
            // idea in its layout's "general" block.
            $table->json('settings')->nullable()->after('right_width');
        });

        // Everything that exists was built for the theme running today.
        DB::table('layout_placements')->update(['theme' => $default]);
        DB::table('layout_columns')->update(['theme' => $default]);

        Schema::table('layout_columns', function (Blueprint $table) {
            $table->dropUnique(['page_type']);
            $table->unique(['theme', 'page_type']);
        });
    }

    public function down(): void
    {
        Schema::table('layout_columns', function (Blueprint $table) {
            $table->dropUnique(['theme', 'page_type']);
            $table->unique(['page_type']);
            $table->dropColumn(['theme', 'settings']);
        });

        Schema::table('layout_placements', function (Blueprint $table) {
            // The index first: SQLite refuses to drop a column an index still
            // names, and rolls the whole migration back with it.
            $table->dropIndex(['theme']);
            $table->dropColumn('theme');
        });
    }
};
