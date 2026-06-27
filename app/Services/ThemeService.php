<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\FooterColumn;
use App\Models\HomepageSection;
use App\Models\NavMenuItem;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ThemeService
{
    protected array $settings = [];
    protected const CACHE_KEY = 'theme_settings';

    /** Per-request memo for the active currency (false = not yet resolved). */
    protected Currency|null|false $activeCurrency = false;

    public function __construct()
    {
        $this->settings = Cache::rememberForever(self::CACHE_KEY, function () {
            $data = [];
            foreach (Setting::all() as $setting) {
                $data[$setting->group][$setting->key] = $setting->value;
            }
            return $data;
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key, 2);
        if (count($parts) === 2) {
            return $this->settings[$parts[0]][$parts[1]] ?? $default;
        }

        return $this->settings[$key] ?? $default;
    }

    public function getJson(string $key, mixed $default = []): mixed
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : $default;
    }

    public function getGroup(string $group): array
    {
        return $this->settings[$group] ?? [];
    }

    public function set(string $group, string $key, ?string $value): void
    {
        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value]
        );

        $this->settings[$group][$key] = $value;
    }

    public function setMany(string $group, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $this->set($group, $key, $value);
        }
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('theme_homepage_sections');
        Cache::forget('theme_nav_items');
        Cache::forget('theme_footer_columns');
        Cache::forget('theme_currencies');
        Cache::forget('nav_subcategories');

        $this->settings = [];
        foreach (Setting::all() as $setting) {
            $this->settings[$setting->group][$setting->key] = $setting->value;
        }
    }

    public function getActiveTheme(): string
    {
        return $this->get('identity.active_theme', 'stage-ready');
    }

    public function getThemePath(?string $theme = null): string
    {
        $theme = $theme ?? $this->getActiveTheme();
        return resource_path("themes/{$theme}");
    }

    public function getInstalledThemes(): array
    {
        $themesPath = resource_path('themes');
        if (!is_dir($themesPath)) {
            return [];
        }

        $themes = [];
        foreach (scandir($themesPath) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            $jsonPath = "{$themesPath}/{$dir}/theme.json";
            if (file_exists($jsonPath)) {
                $meta = json_decode(file_get_contents($jsonPath), true);
                $meta['slug'] = $dir;
                $themes[$dir] = $meta;
            }
        }

        return $themes;
    }

    public function getThemeSettingsSchema(?string $theme = null): array
    {
        $theme = $theme ?? $this->getActiveTheme();
        $jsonPath = $this->getThemePath($theme) . '/theme.json';

        if (!file_exists($jsonPath)) {
            return [];
        }

        $meta = json_decode(file_get_contents($jsonPath), true);
        return $meta['settings'] ?? [];
    }

    public function getThemeSetting(string $key, mixed $default = null): mixed
    {
        $theme = $this->getActiveTheme();
        return $this->get("theme_settings.{$theme}_{$key}", $default);
    }

    public function setThemeSetting(string $key, mixed $value): void
    {
        $theme = $this->getActiveTheme();
        $this->set('theme_settings', "{$theme}_{$key}", $value);
    }

    public function getAllGrouped(): array
    {
        return $this->settings;
    }

    public function getHomepageSections()
    {
        return Cache::rememberForever('theme_homepage_sections', function () {
            return HomepageSection::where('is_enabled', true)
                ->orderBy('sort_order')
                ->get();
        });
    }

    public function getNavItems()
    {
        return Cache::rememberForever('theme_nav_items', function () {
            return NavMenuItem::where('is_enabled', true)
                ->orderBy('sort_order')
                ->with('category')
                ->get();
        });
    }

    public function getFooterColumns()
    {
        return Cache::rememberForever('theme_footer_columns', function () {
            return FooterColumn::where('is_enabled', true)
                ->orderBy('sort_order')
                ->get();
        });
    }

    public function getActiveCurrencies()
    {
        return Cache::rememberForever('theme_currencies', function () {
            return Currency::where('is_enabled', true)
                ->orderByDesc('is_default')
                ->orderBy('code')
                ->get();
        });
    }

    public function getDefaultCurrency(): ?Currency
    {
        return $this->getActiveCurrencies()->firstWhere('is_default', true);
    }

    /**
     * The currency the shopper is currently viewing prices in: the one named by
     * the `currency` cookie (if still enabled), otherwise the store default.
     * Memoized per request. Display-only — orders are still charged in the base
     * (default) currency.
     */
    public function getActiveCurrency(): ?Currency
    {
        if ($this->activeCurrency !== false) {
            return $this->activeCurrency;
        }

        $currencies = $this->getActiveCurrencies();
        $code = request()?->cookie('currency');
        $match = $code ? $currencies->firstWhere('code', $code) : null;

        return $this->activeCurrency = $match ?: $this->getDefaultCurrency();
    }
}
