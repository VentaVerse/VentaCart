<?php

use App\Support\InstallState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Continuity for stores already running: themes are now installable
 * packages recorded in the extensions registry, and a store that has been
 * serving a theme all along must not wake up with "No theme installed".
 * The shipped default and whichever theme the store runs are registered
 * as installed, with the state they already had. A fresh install is left
 * to its seeder, which installs the default theme with the other
 * essentials.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extensions') || ! Schema::hasTable('settings') || InstallState::isFreshInstall()) {
            return;
        }

        $active = (string) DB::table('settings')->where('group', 'identity')->where('key', 'active_theme')->value('value');

        foreach (array_unique(array_filter([\App\Services\ThemeService::THEME, $active])) as $slug) {
            $json = resource_path("themes/{$slug}/theme.json");

            if (! is_file($json)) {
                continue;
            }

            $meta = json_decode((string) file_get_contents($json), true) ?: [];
            $name = \App\Services\ThemeService::registryName($slug);

            if (DB::table('extensions')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('extensions')->insert([
                'name' => $name,
                'version' => (string) ($meta['version'] ?? '1.0.0'),
                'enabled' => true,
                'installed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The registry rows are the record of what a store runs; taking them
        // back would take the storefront down. Nothing to undo.
    }
};
