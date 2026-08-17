<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The theme editor became an extension (owner ruling 2026-08-18). Unlike
 * the wave 1 conversions it owns no tables — the theme system's tables
 * and rendering stay core — so there is no "existing deploy" discriminator
 * and none is wanted: every store, fresh or existing, gets the editor
 * installed and enabled (a store needs its look on day one, the way
 * OpenCart ships its default theme installed). Disable stays available
 * from the directory and only closes the editing surface.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extensions')) {
            return;
        }

        if (DB::table('extensions')->where('name', 'theme')->exists()) {
            return;
        }

        DB::table('extensions')->insert([
            'name' => 'theme',
            'version' => '1.0.0',
            'enabled' => true,
            'installed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The manager caches the registry cross-request; a row written
        // behind its back must bust it or the directory keeps saying
        // "Not installed" until the cache happens to expire.
        \Illuminate\Support\Facades\Cache::forget(\App\Services\ExtensionManager::REGISTRY_CACHE_KEY);
    }

    public function down(): void
    {
        // Continuity seeding is not reversible; the extension row is state,
        // not schema.
    }
};
