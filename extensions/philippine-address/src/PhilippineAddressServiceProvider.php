<?php

namespace Extensions\PhilippineAddress;

use App\Models\Setting;
use App\Services\AddressProviderRegistry;
use Extensions\PhilippineAddress\Models\PhRegion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PhilippineAddressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register as address provider only if enabled
        $this->app->booted(function () {
            if (!$this->isEnabled()) return;

            $registry = $this->app->make(AddressProviderRegistry::class);
            $registry->register(new PhilippineAddressProvider());
        });
    }

    public function boot(): void
    {
        // Routes, migrations, and views always load (admin needs them)
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ph-address');

        // Register seeder so db:seed picks it up automatically
        $this->registerSeeder();

        // Sidebar link for PH Address settings
        View::composer('layouts.admin', function ($view) {
            $view->with('phAddressInstalled', true);
        });

        // Provide data for the Localization page (always — admin feature)
        View::composer('admin.localization.index', function ($view) {
            if (!Schema::hasTable('ph_regions')) return;

            $phRegions = PhRegion::with(['provinces' => function ($q) {
                $q->orderBy('name');
            }])->orderBy('name')->get();

            $phStats = [
                'regions' => DB::table('ph_regions')->count(),
                'provinces' => DB::table('ph_provinces')->count(),
                'cities' => DB::table('ph_cities')->count(),
                'barangays' => DB::table('ph_barangays')->count(),
            ];

            $view->with('phRegions', $phRegions);
            $view->with('phStats', $phStats);
            $view->with('phExtensionInstalled', true);
            $view->with('phExtensionEnabled', $this->isEnabled());
        });
    }

    private function isEnabled(): bool
    {
        try {
            return Setting::where('group', 'extensions')
                ->where('key', 'ph_address_enabled')
                ->value('value') !== '0';
        } catch (\Throwable) {
            return true; // Default enabled if settings table doesn't exist yet
        }
    }

    private function registerSeeder(): void
    {
        $seeders = config('venta.extension_seeders', []);
        $seeders[] = \Extensions\PhilippineAddress\Database\Seeders\PhilippineGeographicSeeder::class;
        config(['venta.extension_seeders' => $seeders]);
    }
}
