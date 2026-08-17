<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEO became an extension, closing wave 1 (the last CoreFeatures tenant;
 * the shim retired with it). An existing deploy arrives installed,
 * honoring the old features.seo_enabled master switch before deleting
 * it. Fresh installs skip this and install from the directory.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extensions') || ! Schema::hasTable('seo_suggestions')) {
            return;
        }

        if (! DB::table('extensions')->where('name', 'seo')->exists()) {
            $masterOff = Schema::hasTable('settings')
                && DB::table('settings')->where('group', 'features')->where('key', 'seo_enabled')->value('value') === '0';

            DB::table('extensions')->insert([
                'name' => 'seo',
                'version' => '1.0.0',
                'enabled' => ! $masterOff,
                'installed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('group', 'features')->where('key', 'seo_enabled')->delete();
        }

        \Illuminate\Support\Facades\Cache::forget(\App\Services\ExtensionManager::REGISTRY_CACHE_KEY);
    }

    public function down(): void
    {
        // Continuity seeding is not reversible.
    }
};
