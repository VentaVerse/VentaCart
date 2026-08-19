<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The four shipping rate methods became extensions (wave 2). An existing
 * deploy already has their tables and settings, so each arrives
 * installed, honoring its old shipping.*_enabled switch (missing switch
 * meant OFF for the paid methods; free shipping never had one and is
 * enabled by presence). The old switch settings are then deleted: the
 * extension row is the switch now. Fresh installs skip this and install
 * from the directory.
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

        if (! Schema::hasTable('extensions') || ! Schema::hasTable('settings')) {
            return;
        }

        $setting = fn (string $key) => DB::table('settings')
            ->where('group', 'shipping')->where('key', $key)->value('value');

        $seed = function (string $name, bool $enabled) {
            if (DB::table('extensions')->where('name', $name)->exists()) {
                return;
            }
            DB::table('extensions')->insert([
                'name' => $name,
                'version' => '1.0.0',
                'enabled' => $enabled,
                'installed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        if (Schema::hasTable('free_shipping_rules')) {
            $seed('shipping-free', true);
        }
        if (Schema::hasTable('weight_based_methods')) {
            $seed('shipping-weight-based', $setting('weight_based_enabled') === '1');
        }
        if (Schema::hasTable('flat_shipping_rates')) {
            $seed('shipping-flat-rate', $setting('flat_rate_enabled') === '1');
        }
        if ($setting('dhl_enabled') !== null || $setting('dhl_site_id') !== null) {
            $seed('shipping-dhl', $setting('dhl_enabled') === '1');
        }

        DB::table('settings')->where('group', 'shipping')
            ->whereIn('key', ['weight_based_enabled', 'flat_rate_enabled', 'dhl_enabled'])
            ->delete();

        \Illuminate\Support\Facades\Cache::forget(\App\Services\ExtensionManager::REGISTRY_CACHE_KEY);
    }

    public function down(): void
    {
        // Continuity seeding is not reversible; the extension rows are
        // state, not schema.
    }
};
