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
     * The settings boot reads (site name, mail, time zone, admin prefix,
     * address lookups), by group and key, from one query; null when the
     * settings table cannot be read (a fresh install, a migration).
     *
     * @var array<string, array<string, ?string>>|null
     */
    private ?array $bootSettings = null;

    private bool $bootSettingsLoaded = false;

    /** Single settings boot needs, by group => key. The whole mail group is read too. */
    private const BOOT_SETTINGS = [
        ['identity', 'site_name'],
        ['general', 'timezone'],
        ['security', 'admin_prefix'],
        ['extensions', 'ph_address_enabled'],
    ];

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
        // A migration can create or drop a table SchemaState remembers; a
        // queue worker runs for days, so each job starts from what is true now.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\MigrationsEnded::class, fn () => \App\Support\SchemaState::forget());
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\JobProcessing::class, fn () => \App\Support\SchemaState::forgetInProcess());

        // Package uploads install code; a few a minute is plenty for a person.
        \Illuminate\Support\Facades\RateLimiter::for('package-uploads', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(max(1, (int) config('extensions.upload.per_minute', 5)))
            ->by('package-uploads:' . ($request->user()?->id ?: $request->ip())));

        // Shipping quotes (the cart's estimate, checkout's delivery options)
        // ask every shipping provider, and some call a courier's API: a
        // loop of them would tie up PHP workers and spend the store's courier
        // quota. Signed-in customers are counted by account, guests by IP.
        \Illuminate\Support\Facades\RateLimiter::for('shipping-quotes', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(max(1, (int) config('shipping.quotes_per_minute', 20)))
            ->by('shipping-quotes:' . (($id = \Illuminate\Support\Facades\Auth::guard('customer')->id()) ? 'customer:' . $id : 'ip:' . $request->ip()))
            ->response(fn (\Illuminate\Http\Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => 'Too many shipping lookups in a short time. Wait a minute, then try again.',
                'data' => null,
            ], 429, $headers)));

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
        // The sender is the store: its email and site name, from General.
        // Forget any identity resolved before this boot so a long-lived
        // process never mails from a store email that has since changed.
        \App\Support\StoreIdentity::flush();
        \App\Support\MailSender::apply();
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
                $settings = $this->bootSettings();
                if ($settings === null) {
                    return;
                }
                $enabled = $settings['extensions']['ph_address_enabled'] ?? null;

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

    /**
     * Every setting boot reads, from one query instead of one per setting.
     *
     * @return array<string, array<string, ?string>>|null
     */
    private function bootSettings(): ?array
    {
        if ($this->bootSettingsLoaded) {
            return $this->bootSettings;
        }
        $this->bootSettingsLoaded = true;

        try {
            $rows = Setting::query()
                ->where(function ($query) {
                    $query->where('group', 'mail');
                    foreach (self::BOOT_SETTINGS as [$group, $key]) {
                        $query->orWhere(fn ($q) => $q->where('group', $group)->where('key', $key));
                    }
                })
                ->get(['group', 'key', 'value']);
        } catch (\Throwable) {
            return $this->bootSettings = null;
        }

        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row->group][(string) $row->key] = $row->value;
        }

        return $this->bootSettings = $settings;
    }

    private function configureTimezoneFromDatabase(): void
    {
        $timezone = $this->bootSettings()['general']['timezone'] ?? null;

        if ($timezone && in_array($timezone, timezone_identifiers_list())) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }

    private function configureAppNameFromDatabase(): void
    {
        $siteName = $this->bootSettings()['identity']['site_name'] ?? null;
        if ($siteName) {
            config(['app.name' => $siteName]);
        }
    }

    private function configureAdminPrefixFromDatabase(): void
    {
        $prefix = $this->bootSettings()['security']['admin_prefix'] ?? null;

        if ($prefix && preg_match('/^[a-z][a-z0-9-]*$/', $prefix)) {
            config(['admin.prefix' => $prefix]);
        }
    }

    private function configureMailFromDatabase(): void
    {
        $settings = $this->bootSettings();
        if ($settings === null) {
            return; // Table may not exist during migrations
        }
        $mail = collect($settings['mail'] ?? [])->filter(fn ($v) => $v !== null && $v !== '');

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
    }
}
