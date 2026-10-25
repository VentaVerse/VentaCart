<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The menu composer. Menus become records (a store may
 * compose several: main, footer, a mobile one) and items gain a parent, so
 * a menu is a tree; a page to point at; and a settings bag for the richer
 * item types (mega menu columns, banner, brands, an HTML dropdown, accent).
 *
 * Continuity: every existing item moves under the "main" menu, created
 * here if it does not exist. Fresh installs get the same menu from the
 * seeder; creating it here as well keeps the column non-null either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_menus', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->timestamps();
        });

        $mainId = DB::table('nav_menus')->insertGetId(['key' => 'main', 'name' => 'Main menu', 'created_at' => now(), 'updated_at' => now()]);

        Schema::table('nav_menu_items', function (Blueprint $table) {
            $table->foreignId('menu_id')->nullable()->after('id')->constrained('nav_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->after('menu_id')->constrained('nav_menu_items')->nullOnDelete();
            $table->foreignId('page_id')->nullable()->after('category_id')->constrained('pages')->nullOnDelete();
            $table->json('config')->nullable()->after('new_tab');
        });

        DB::table('nav_menu_items')->whereNull('menu_id')->update(['menu_id' => $mainId]);
    }

    public function down(): void
    {
        Schema::table('nav_menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('page_id');
            $table->dropConstrainedForeignId('menu_id');
            $table->dropColumn('config');
        });

        Schema::dropIfExists('nav_menus');
    }
};
