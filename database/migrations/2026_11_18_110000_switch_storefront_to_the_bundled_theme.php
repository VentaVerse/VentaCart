<?php

use App\Models\Setting;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\InstallState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A store whose live theme can no longer draw is moved onto the theme that
 * ships with VentaCart: the theme is installed the way the Install button
 * installs it (contract check, the theme's own migrations), and made live.
 *
 * "Can no longer draw" means the theme's folder is gone, or it is not an
 * installed, enabled package. A store whose theme still draws, a theme of
 * its own choosing included, is left alone. A fresh install has nothing to
 * move: it installs the bundled theme when it is seeded.
 *
 * When the install fails the migration fails, and the deploy stops with
 * the store's data untouched, so nothing that depends on the switch runs
 * while the store has no theme. Safe to run again: a store already on a
 * theme that draws is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (InstallState::isFreshInstall()) {
            return;
        }

        $bundled = ThemeService::bundled();
        $stored = (string) DB::table('settings')->where('group', 'identity')->where('key', 'active_theme')->value('value');

        if ($stored !== '' && $this->draws($stored)) {
            return;
        }

        // A VentaCart without its bundled theme has nothing to switch to;
        // its storefront shows the plain "No theme installed" page.
        if (! is_file(ThemeService::root($bundled . '/theme.json'))) {
            return;
        }

        $manager = app(ExtensionManager::class);
        $manager->rescan();
        $name = ThemeService::registryName($bundled);

        if ($manager->state($name) !== 'enabled') {
            $manager->install($name);
        }
        if ($manager->state($name) !== 'enabled') {
            $manager->enable($name);
        }

        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => $bundled]);
        app(ThemeService::class)->clearCache();
    }

    /** The store has nothing to put back: the previous theme could not draw. */
    public function down(): void
    {
    }

    private function draws(string $slug): bool
    {
        return is_file(ThemeService::root($slug . '/theme.json'))
            && DB::table('extensions')->where('name', ThemeService::registryName($slug))->where('enabled', true)->exists();
    }
};
