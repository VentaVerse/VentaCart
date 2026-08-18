<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons became an extension (order-totals wave). An existing deploy
 * already has its tables and live coupons, so it arrives installed and
 * enabled. Fresh installs skip this and install from the directory.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extensions') || ! Schema::hasTable('coupons')) {
            return;
        }

        if (DB::table('extensions')->where('name', 'coupons')->exists()) {
            return;
        }

        DB::table('extensions')->insert([
            'name' => 'coupons',
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
