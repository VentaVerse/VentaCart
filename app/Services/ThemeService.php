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
    /** The only theme. Folder under resources/themes. */
    public const THEME = 'venta';

    protected array $settings = [];
    protected const CACHE_KEY = 'theme_settings';

    /** Contract results for themes awaiting an update, by slug, version and file time. */
    private array $servable = [];

    /** Per-request memo for the active currency (false = not yet resolved). */
    protected Currency|null|false $activeCurrency = false;

    public function __construct()
    {
        $this->reload();
    }

    /**
     * The folder themes live in (resources/themes unless
     * extensions.themes_path says otherwise), or $path inside it.
     */
    public static function root(string $path = ''): string
    {
        $root = rtrim((string) (config('extensions.themes_path') ?: resource_path('themes')), '/');

        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }

    /** Read the stored settings again, dropping any overlay. */
    public function reload(): void
    {
        $this->settings = Cache::rememberForever(self::CACHE_KEY, function () {
            $data = [];
            foreach (Setting::all() as $setting) {
                $data[$setting->group][$setting->key] = $setting->value;
            }
            return $data;
        });
        if (class_exists(\App\Support\ThemeOptions::class)) {
            \App\Support\ThemeOptions::flush();
        }
    }

    /**
     * Lay unsaved values over the stored settings for the rest of this
     * request: a theme's editor previewing a draft, or editing a theme that
     * is not the live one. Nothing is written; the next request reads the
     * store as it is.
     *
     * @param  array<string, array<string, mixed>>  $groups  group => [key => value]
     */
    public function overlay(array $groups): void
    {
        foreach ($groups as $group => $values) {
            foreach ((array) $values as $key => $value) {
                $this->settings[$group][$key] = $value;
            }
        }
        // Per-request memo of theme options reads through this service.
        if (class_exists(\App\Support\ThemeOptions::class) && method_exists(\App\Support\ThemeOptions::class, 'flush')) {
            \App\Support\ThemeOptions::flush();
        }
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
        Cache::forget('nav_subcategories');
        \App\Support\NavTree::forget();
        \App\Support\ThemeOptions::forget();
        Cache::forget('theme_footer_columns');
        Cache::forget('theme_currencies');
        Cache::forget('nav_subcategories');

        $this->settings = [];
        foreach (Setting::all() as $setting) {
            $this->settings[$setting->group][$setting->key] = $setting->value;
        }
    }

    /**
     * Which theme the storefront runs.
     *
     * Read from settings again (owner ruling 2026-08-21), because a theme is
     * a folder of views and CSS once more, not a palette. Between 2026-08-20
     * and that ruling this returned a constant: the three legacy folders were
     * near-copies of one skeleton, so switching them only ever changed colour.
     *
     * The guard that made hardcoding tempting stays, and matters more now that
     * the value is honoured: a stored theme whose FOLDER IS GONE falls back to
     * the default. Resolving views into a directory that does not exist is a
     * white storefront, not a wrong colour, and an uninstalled theme must not
     * be able to take the shop down.
     */
    public function getActiveTheme(): string
    {
        $slug = (string) $this->get('identity.active_theme', self::THEME);

        if ($slug === '' || ! is_file(self::root("{$slug}/theme.json"))) {
            return self::THEME;
        }

        return $slug;
    }

    /** The registry name a theme package installs under. */
    public static function registryName(string $slug): string
    {
        return 'theme-' . $slug;
    }

    /**
     * Whether the storefront has a theme to draw with: the one it is
     * pointed at is installed and its folder is on disk. A theme awaiting an
     * update counts as installed while its files keep the contract. When
     * not, core shows its plain "No theme installed" page.
     */
    public function hasActiveTheme(): bool
    {
        return isset($this->getInstalledThemes()[$this->getActiveTheme()]);
    }

    /**
     * Installed themes, by slug: available packages the store has installed
     * (a registry row, enabled). Only these can be made live.
     */
    public function getInstalledThemes(): array
    {
        return array_filter($this->getAvailableThemes(), fn ($meta) => $meta['installed']);
    }

    /**
     * Every theme package on disk, by slug, with whether it is installed.
     * A theme is a folder under resources/themes with a theme.json.
     */
    public function getAvailableThemes(): array
    {
        $dir = self::root();

        if (! is_dir($dir)) {
            return [];
        }

        $themes = [];

        foreach (scandir($dir) as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }

            $json = "{$dir}/{$slug}/theme.json";

            if (! is_file($json)) {
                continue;
            }

            $meta = json_decode((string) file_get_contents($json), true) ?: [];
            $meta['slug'] = $slug;
            $meta['name'] = $meta['name'] ?? ucfirst(str_replace('-', ' ', $slug));
            $meta['is_default'] = $slug === self::THEME;
            $extensions = app(ExtensionManager::class);
            $meta['state'] = $extensions->state(self::registryName($slug));
            $meta['update_available'] = $meta['state'] === 'needs_migration';
            $meta['installed_version'] = $extensions->registry()->get(self::registryName($slug))?->version;
            $meta['installed'] = $meta['state'] === 'enabled'
                || ($meta['update_available'] && $this->servableWhileAwaitingUpdate($slug, $meta['installed_version']));
            $themes[$slug] = $meta;
        }

        ksort($themes);

        return $themes;
    }

    /**
     * Whether a theme whose files are newer than its installed version may
     * keep drawing the storefront until the update is run. It may, so that
     * copying new files over a live theme never takes the shop down, unless
     * the new files no longer keep the theme contract: a package missing a
     * view the store renders would break pages, and the plain page is the
     * safer answer. The check reads files, so it runs once per theme and
     * version for the life of this service.
     */
    private function servableWhileAwaitingUpdate(string $slug, ?string $installedVersion): bool
    {
        $row = app(ExtensionManager::class)->registry()->get(self::registryName($slug));
        if (! $row || ! $row->enabled) {
            return false;
        }

        $path = self::root($slug);
        $key = $slug . '@' . $installedVersion . '@' . (string) @filemtime($path . '/theme.json');

        return $this->servable[$key] ??= \App\Support\ThemeContract::check($path) === [];
    }

    /**
     * A theme's shipped arrangement (layouts/home.json), installed on a theme
     * that has no rows yet. True when something was installed.
     */
    private function installShippedLayout(string $theme): bool
    {
        if (\App\Models\LayoutPlacement::where('theme', $theme)->exists() || \App\Models\LayoutRow::where('theme', $theme)->exists()) {
            return true;
        }
        $file = $this->getThemePath($theme) . '/layouts/home.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data) || empty($data['rows'])) {
            return false;
        }
        $region = (string) ($data['region'] ?? 'content_top');
        $rows = array_map(fn ($row) => $row + ['id' => null, 'region' => $region], \App\Models\LayoutTemplate::cleanRows($data['rows']));
        // The theme's own file: trusted, whoever switches the theme on.
        app(LayoutSaver::class)->save('home', ['columns' => ['left' => 0, 'right' => 0], 'rows' => $rows], trusted: true);

        return true;
    }

    /**
     * Whether a theme keeps its look and layouts in storage of its own
     * (`"storage": "own"` in theme.json). Such a theme only reads core, so
     * core never writes looks or layout rows on its behalf.
     */
    public function keepsOwnStorage(string $slug): bool
    {
        $meta = json_decode((string) @file_get_contents($this->getThemePath($slug) . '/theme.json'), true);

        return is_array($meta) && ($meta['storage'] ?? null) === 'own';
    }

    /**
     * The route of a theme's own editor (`"editor"` in theme.json), when it
     * declares one and the route is registered.
     */
    public function editorRoute(string $slug): ?string
    {
        $meta = json_decode((string) @file_get_contents($this->getThemePath($slug) . '/theme.json'), true);
        $route = is_array($meta) ? ($meta['editor'] ?? null) : null;

        return is_string($route) && $route !== '' && \Illuminate\Support\Facades\Route::has($route) ? $route : null;
    }

    /** Point the storefront at another installed theme. */
    public function activateTheme(string $slug): void
    {
        if (! isset($this->getInstalledThemes()[$slug])) {
            throw new \InvalidArgumentException("Theme [{$slug}] is not installed.");
        }

        $from = $this->getActiveTheme();
        $switching = $from !== $slug;

        // Layout rows in core belong to themes that store them in core. A
        // theme with storage of its own neither leaves rows here nor takes them.
        $fromInCore = ! $this->keepsOwnStorage($from);
        $toInCore = ! $this->keepsOwnStorage($slug);

        Setting::updateOrCreate(
            ['group' => 'identity', 'key' => 'active_theme'],
            ['value' => $slug]
        );
        $this->settings['identity']['active_theme'] = $slug;

        // A theme opened for the first time has no layout of its own. It
        // shows what it ships (layouts/home.json) when it ships one, so
        // nothing of the previous theme is assumed; a theme without a
        // shipped arrangement takes the merchant's rows along instead, when
        // the previous theme kept its rows in core.
        if ($switching && $toInCore && ! $this->installShippedLayout($slug) && $fromInCore) {
            $this->carryLayout($from, $slug);
        }

        $this->clearCache();
    }

    /** Copy one theme's rows, placements and page columns onto another that has none. */
    public function carryLayout(string $from, string $to): void
    {
        $hasOwn = \App\Models\LayoutPlacement::where('theme', $to)->exists()
            || \App\Models\LayoutRow::where('theme', $to)->exists();
        if ($hasOwn) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($from, $to) {
            $rowMap = [];
            foreach (\App\Models\LayoutRow::where('theme', $from)->orderBy('id')->get() as $row) {
                $copy = $row->replicate();
                $copy->theme = $to;
                $copy->save();
                $rowMap[$row->id] = $copy->id;
            }
            foreach (\App\Models\LayoutPlacement::where('theme', $from)->orderBy('id')->get() as $placement) {
                $copy = $placement->replicate();
                $copy->theme = $to;
                $copy->row_id = $placement->row_id ? ($rowMap[$placement->row_id] ?? null) : null;
                $copy->save();
            }
            foreach (\App\Models\LayoutColumns::where('theme', $from)->get() as $columns) {
                \App\Models\LayoutColumns::updateOrCreate(
                    ['theme' => $to, 'page_type' => $columns->page_type],
                    ['left_width' => $columns->left_width, 'right_width' => $columns->right_width, 'settings' => $columns->settings]
                );
            }
        });
    }

    public function getThemePath(?string $theme = null): string
    {
        $theme = $theme ?? $this->getActiveTheme();
        return self::root($theme);
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
