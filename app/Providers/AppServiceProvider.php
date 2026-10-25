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

        // Vite that also serves the builds uploaded packages bring with them.
        $this->app->singleton(\Illuminate\Foundation\Vite::class, \App\Support\PackageVite::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // What can be placed in a theme's regions. Registered at boot, never
        // per request: the registry is process-global, like the courier one.
        \App\Storefront\Widgets\CoreWidgets::register();

        // Package uploads install code; a few a minute is plenty for a person.
        \Illuminate\Support\Facades\RateLimiter::for('package-uploads', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(max(1, (int) config('extensions.upload.per_minute', 5)))
            ->by('package-uploads:' . ($request->user()?->id ?: $request->ip())));

        // The sidebar's work-waiting counts (Fulfillment to ship, Reviews
        // pending). Bound here so the shell never learns which entries
        // are special; it just looks its items up (see NavBadges).
        // Composed onto chrome: the component that actually renders the
        // nav (the v3->v rename split the old shell into shell + chrome,
        // and component views do not inherit a parent view's variables).
        View::composer('components.admin.chrome', function ($view) {
            $view->with('navBadges', \App\Support\NavBadges::counts());
        });

        // Core's own morphable types. An extension's model is added only when
        // that extension is actually present: Blog is optional and can be
        // uninstalled, and a map entry pointing at a class that is not loaded
        // fails at the moment something resolves the type, not at boot, so it
        // surfaced as a 500 on unrelated admin pages rather than anything that
        // named Blog. class_exists() is the check, not "is it enabled": rows
        // already carrying a blog_post morph must still resolve on a store that
        // has merely switched the module off.
        $morphMap = [
            'product' => \App\Models\Product::class,
            'category' => \App\Models\Category::class,
            'brand' => \App\Models\Brand::class,
            'page' => \App\Models\Page::class,
        ];

        if (class_exists(\Extensions\Blog\Models\BlogPost::class)) {
            $morphMap['blog_post'] = \Extensions\Blog\Models\BlogPost::class;
        }

        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap($morphMap);

        $this->configureAppNameFromDatabase();
        $this->configureMailFromDatabase();
        $this->configureTimezoneFromDatabase();
        $this->configureAdminPrefixFromDatabase();

        $this->app->booted(function () {
            try {
                $theme = app(ThemeService::class)->getActiveTheme();
                $themePath = ThemeService::root("{$theme}/views");

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
        // Payment methods self-register from their extensions (wave 2);
        // core registers none.

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
