<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\AddressProviderRegistry;
use App\Services\ThemeService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AddressProviderRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMailFromDatabase();
        $this->configureTimezoneFromDatabase();

        $this->app->booted(function () {
            try {
                $theme = app(ThemeService::class)->getActiveTheme();
                $themePath = resource_path("themes/{$theme}/views");

                if (is_dir($themePath)) {
                    View::getFinder()->prependLocation($themePath);
                }
            } catch (\Throwable) {
                // ThemeService may fail during migrations
            }
        });

        // Expose address provider manifest to storefront layouts
        View::composer('layouts.store', function ($view) {
            $registry = app(AddressProviderRegistry::class);
            $view->with('addressProviders', $registry->toFrontendManifest());
        });
    }

    private function configureTimezoneFromDatabase(): void
    {
        try {
            $timezone = Setting::where('group', 'general')
                ->where('key', 'timezone')
                ->value('value');
        } catch (\Throwable) {
            return;
        }

        if ($timezone && in_array($timezone, timezone_identifiers_list())) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }

    private function configureMailFromDatabase(): void
    {
        try {
            $mail = Setting::where('group', 'mail')->pluck('value', 'key');
        } catch (\Throwable) {
            return; // Table may not exist during migrations
        }

        if ($mail->isEmpty()) return;

        $driver = $mail->get('driver', 'smtp');

        if ($driver === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $mail->get('host', '127.0.0.1'),
                'mail.mailers.smtp.port' => (int) $mail->get('port', 587),
                'mail.mailers.smtp.username' => $mail->get('username'),
                'mail.mailers.smtp.password' => $mail->get('password'),
                'mail.mailers.smtp.encryption' => $mail->get('encryption', 'tls') ?: null,
            ]);
        } elseif ($driver === 'mail') {
            config([
                'mail.default' => 'sendmail',
            ]);
        }

        if ($mail->get('from_address')) {
            config([
                'mail.from.address' => $mail->get('from_address'),
                'mail.from.name' => $mail->get('from_name', config('app.name')),
            ]);
        }
    }
}
