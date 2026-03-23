<?php

namespace Extensions\PhilippineAddress;

use Extensions\PhilippineAddress\Models\PhRegion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PhilippineAddressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register model aliases so existing code using App\Models\Ph* still works
        $this->app->bind(\App\Models\PhRegion::class, PhRegion::class);
        $this->app->bind(\App\Models\PhProvince::class, Models\PhProvince::class);
        $this->app->bind(\App\Models\PhCity::class, Models\PhCity::class);
        $this->app->bind(\App\Models\PhBarangay::class, Models\PhBarangay::class);
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ph-address');

        // Inject PH phone code (+63) into storefront layouts so address forms show the correct prefix
        // even when Philippines is disabled in the core Countries table
        View::composer(['layouts.store', 'themes.*.layouts.store'], function ($view) {
            $view->with('phExtensionPhoneCode', '63');
        });

        // Provide data for the Localization page's PH Reference tab
        View::composer('admin.localization.index', function ($view) {
            if (!Schema::hasTable('ph_regions')) {
                return;
            }

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
        });
    }
}
