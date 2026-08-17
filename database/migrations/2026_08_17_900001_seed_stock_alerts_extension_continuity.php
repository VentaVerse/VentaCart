<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock Alerts became an extension (architecture wave 1). An EXISTING
 * deploy already has its tables and live waitlist rows, so the extension
 * arrives installed and enabled — behavior unchanged through the deploy —
 * honoring the old CoreFeatures master switch if the merchant had turned
 * it off. Fresh installs skip this (no legacy tables) and enable the
 * extension from the directory like any other.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extensions') || ! Schema::hasTable('stock_notifications')) {
            return;
        }

        if (DB::table('extensions')->where('name', 'stock-alerts')->exists()) {
            return;
        }

        $masterOff = Schema::hasTable('settings')
            && DB::table('settings')->where('group', 'features')->where('key', 'stock_alerts_enabled')->value('value') === '0';

        DB::table('extensions')->insert([
            'name' => 'stock-alerts',
            'version' => '1.0.0',
            'enabled' => ! $masterOff,
            'installed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('group', 'features')->where('key', 'stock_alerts_enabled')->delete();
        }

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
