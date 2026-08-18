<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ERP push endpoints became an extension (wave 3 closes). A deploy
 * that ever generated an ERP token was using them, so it arrives
 * installed and enabled. Fresh installs skip this and install from the
 * directory when they connect an ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extensions') || ! Schema::hasTable('settings')) {
            return;
        }

        $hasToken = DB::table('settings')->where('group', 'api')->where('key', 'erp_token')->exists();

        if (! $hasToken || DB::table('extensions')->where('name', 'erp-sync')->exists()) {
            return;
        }

        DB::table('extensions')->insert([
            'name' => 'erp-sync',
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
