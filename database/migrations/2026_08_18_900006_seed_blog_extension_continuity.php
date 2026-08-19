<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blog became an extension (wave 3). An existing deploy already has its
 * tables and posts, so it arrives installed and enabled. Fresh installs
 * skip this and install from the directory.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Continuity carries an EXISTING store's choices across. A fresh
        // install has none to carry, and its defaults are ExtensionSeeder's
        // job (owner ruling 2026-08-19). See InstallState for why the earlier
        // hasTable() test could not tell the two apart.
        if (\App\Support\InstallState::isFreshInstall()) {
            return;
        }

        if (! Schema::hasTable('extensions') || ! Schema::hasTable('blog_posts')) {
            return;
        }

        if (DB::table('extensions')->where('name', 'blog')->exists()) {
            return;
        }

        DB::table('extensions')->insert([
            'name' => 'blog',
            'version' => '1.0.0',
            'enabled' => true,
            'installed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\Cache::forget(\App\Services\ExtensionManager::REGISTRY_CACHE_KEY);
    }

    public function down(): void
    {
        // Continuity seeding is not reversible.
    }
};
