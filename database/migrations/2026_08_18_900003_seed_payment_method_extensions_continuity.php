<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three payment methods became extensions (wave 2). An existing
 * deploy arrives with each installed, honoring its old
 * payment.{family}_enabled switch (missing meant OFF); the old switch
 * settings are then deleted — the extension row is the switch now.
 * Fresh installs skip this and install from the directory.
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
            ->where('group', 'payment')->where('key', $key)->value('value');

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

        // A deploy that has ever configured payments has at least one of
        // these settings rows; a fresh install has none and skips.
        $legacy = DB::table('settings')->where('group', 'payment')->exists();

        if (Schema::hasTable('bank_accounts') || $legacy) {
            $seed('payment-bank-transfer', $setting('bank_transfer_enabled') === '1');
        }
        if ($legacy) {
            $seed('payment-hitpay', $setting('hitpay_enabled') === '1');
            $seed('payment-cod', $setting('cod_enabled') === '1');
        }

        DB::table('settings')->where('group', 'payment')
            ->whereIn('key', ['bank_transfer_enabled', 'hitpay_enabled', 'cod_enabled'])
            ->delete();

        \Illuminate\Support\Facades\Cache::forget(\App\Services\ExtensionManager::REGISTRY_CACHE_KEY);
    }

    public function down(): void
    {
        // Continuity seeding is not reversible; the extension rows are
        // state, not schema.
    }
};
