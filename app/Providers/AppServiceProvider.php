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
        // The sidebar's work-waiting counts (Fulfillment to ship, Reviews
        // pending). Bound here so the shell never learns which entries
        // are special; it just looks its items up (see NavBadges).
        View::composer('components.admin.shell', function ($view) {
            $view->with('navBadges', \App\Support\NavBadges::counts());
        });

        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
            'product' => \App\Models\Product::class,
            'category' => \App\Models\Category::class,
            'brand' => \App\Models\Brand::class,
            'blog_post' => \App\Models\BlogPost::class,
            'page' => \App\Models\Page::class,
        ]);

        $this->configureAppNameFromDatabase();
        $this->configureMailFromDatabase();
        $this->configureTimezoneFromDatabase();
        $this->configureAdminPrefixFromDatabase();

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

        $this->registerDeepAddressProviders();

        // Payment methods sign in here (see PaymentManager docblock). Extensions
        // add their own register() call in their own service provider.
        \App\Services\PaymentManager::register(\App\Services\PaymentMethods\BankTransferPayment::class);
        \App\Services\PaymentManager::register(\App\Services\PaymentMethods\HitPayPayment::class);
        \App\Services\PaymentManager::register(\App\Services\PaymentMethods\CodPayment::class);

        // Expose address provider manifest to storefront layouts
        View::composer('layouts.store', function ($view) {
            $registry = app(AddressProviderRegistry::class);
            $view->with('addressProviders', $registry->toFrontendManifest());
        });
    }

    /**
     * Register config-driven deep-address providers (e.g. PH city/barangay).
     * Gated by the `ph_address_enabled` setting (default on); skips cleanly if
     * the settings table isn't available yet (e.g. during migrations).
     */
    private function registerDeepAddressProviders(): void
    {
        $this->app->booted(function () {
            try {
                $enabled = Setting::where('group', 'extensions')
                    ->where('key', 'ph_address_enabled')
                    ->value('value');

                if ($enabled === '0') {
                    return;
                }

                $registry = app(AddressProviderRegistry::class);
                foreach (config('localization.deep_address', []) as $iso => $config) {
                    $registry->register(new \App\Services\Address\DeepAddressProvider($iso, $config));
                }
            } catch (\Throwable) {
                // Settings table or DB may be unavailable during early boot/migrations.
            }
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

    private function configureAppNameFromDatabase(): void
    {
        try {
            $siteName = Setting::where('group', 'identity')->where('key', 'site_name')->value('value');
            if ($siteName) {
                config(['app.name' => $siteName]);
            }
        } catch (\Throwable $e) {
            // DB may not be available during migrations
        }
    }

    private function configureAdminPrefixFromDatabase(): void
    {
        try {
            $prefix = Setting::where('group', 'security')
                ->where('key', 'admin_prefix')
                ->value('value');
        } catch (\Throwable) {
            return;
        }

        if ($prefix && preg_match('/^[a-z][a-z0-9-]*$/', $prefix)) {
            config(['admin.prefix' => $prefix]);
        }
    }

    private function configureMailFromDatabase(): void
    {
        try {
            $mail = Setting::where('group', 'mail')->pluck('value', 'key')
                ->filter(fn ($v) => $v !== null && $v !== '');
        } catch (\Throwable) {
            return; // Table may not exist during migrations
        }

        if ($mail->isEmpty()) return;

        $driver = $mail->get('driver', 'smtp');

        // Without a host the transport cannot be built; keep the .env mailer
        if ($driver === 'smtp' && $mail->get('host')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $mail->get('host'),
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
