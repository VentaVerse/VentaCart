<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews became an extension (wave 3). An existing deploy already has
 * its tables and live reviews, so it arrives installed and enabled.
 * Fresh installs skip this and install from the directory.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extensions') || ! Schema::hasTable('reviews')) {
            return;
        }

        if (DB::table('extensions')->where('name', 'reviews')->exists()) {
            return;
        }

        DB::table('extensions')->insert([
            'name' => 'reviews',
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
