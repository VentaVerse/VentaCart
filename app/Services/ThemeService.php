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

    /** Per-request memo for the active currency (false = not yet resolved). */
    protected Currency|null|false $activeCurrency = false;

    public function __construct()
    {
        $this->reload();
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

    /**
     * The settings group holding one page type's overrides for one family.
     *
     * Deliberately NOT the "{family}__{slug}" shape. That namespace already
     * belongs to preset snapshots, and a page type sharing a name with a preset
     * would silently write into it: a merchant customising the account pages
     * would corrupt a preset they never opened, and the damage would only
     * surface the next time they applied it.
     */
    public function pageOverrideGroup(string $type, string $family): string
    {
        return "page_override:{$type}:{$family}";
    }

    /** One page type's overrides for one family (colors, fonts, vars). */
    public function pageOverrides(?string $type, string $family): array
    {
        if (! \App\Support\StorefrontPageType::exists($type)) {
            return [];
        }

        return $this->getGroup($this->pageOverrideGroup($type, $family));
    }

    /** Every page type that overrides anything, as type => number of values. */
    public function pageOverrideCounts(): array
    {
        $counts = [];

        foreach (array_keys(\App\Support\StorefrontPageType::TYPES) as $type) {
            $n = 0;
            foreach (['colors', 'fonts', 'vars', 'theme_settings'] as $family) {
                $n += count($this->getGroup($this->pageOverrideGroup($type, $family)));
            }
            if ($n > 0) {
                $counts[$type] = $n;
            }
        }

        return $counts;
    }

    /**
     * Write one page type's overrides for one family.
     *
     * A value equal to the global one is DELETED rather than stored. An
     * override that matches what it overrides is not a decision, it is noise:
     * it would carry a "this page differs" mark that lies, and it would survive
     * a later global edit as a silent pin.
     */
    public function setPageOverrides(string $type, string $family, array $values): void
    {
        if (! \App\Support\StorefrontPageType::exists($type)) {
            throw new \InvalidArgumentException("Unknown page type [{$type}].");
        }

        $group = $this->pageOverrideGroup($type, $family);
        // Read the global from the database, not the in-memory settings cache.
        // A caller that saves the store's colours and a page's overrides in one
        // request would otherwise compare against pre-save values and store an
        // override identical to the global it was meant to differ from.
        $global = Setting::where('group', $family)->pluck('value', 'key')->all();

        foreach ($values as $key => $value) {
            if ($value === null || $value === '' || (array_key_exists($key, $global) && (string) $global[$key] === (string) $value)) {
                Setting::where('group', $group)->where('key', $key)->delete();
                continue;
            }

            Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
        }

        $this->clearCache();
    }

    /** Drop every override a page type carries. Used when a preset is applied. */
    public function clearPageOverrides(?string $type = null): int
    {
        $types = $type !== null ? [$type] : array_keys(\App\Support\StorefrontPageType::TYPES);
        $deleted = 0;

        foreach ($types as $t) {
            foreach (['colors', 'fonts', 'vars', 'theme_settings'] as $family) {
                $deleted += Setting::where('group', $this->pageOverrideGroup($t, $family))->delete();
            }
        }

        $this->clearCache();

        return $deleted;
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

        if ($slug === '' || ! is_file(resource_path("themes/{$slug}/theme.json"))) {
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
     * pointed at is installed and its folder is on disk. When not, core
     * shows its plain "No theme installed" page.
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
        $dir = resource_path('themes');

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
            $meta['state'] = app(ExtensionManager::class)->state(self::registryName($slug));
            $meta['installed'] = $meta['state'] === 'enabled';
            $themes[$slug] = $meta;
        }

        ksort($themes);

        return $themes;
    }

    /** Point the storefront at another installed theme. */
    public function activateTheme(string $slug): void
    {
        if (! isset($this->getInstalledThemes()[$slug])) {
            throw new \InvalidArgumentException("Theme [{$slug}] is not installed.");
        }

        $from = $this->getActiveTheme();

        Setting::updateOrCreate(
            ['group' => 'identity', 'key' => 'active_theme'],
            ['value' => $slug]
        );

        // A theme opened for the first time has no layout of its own, and a
        // storefront with empty positions is not what anyone meant by
        // "use this theme". The merchant's arrangement travels with them:
        // copied, so the theme they left keeps its own, and only when the
        // new theme has nothing, so a theme they come back to keeps what
        // they built there.
        if ($from !== $slug) {
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
        return resource_path("themes/{$theme}");
    }

    /**
     * Presets installed with the active theme: each is a JSON overlay
     * (colors, fonts, options) over the theme.json base contract.
     */
    public function getInstalledPresets(?string $theme = null): array
    {
        $presets = [];

        // Shipped first, then the merchant's, so a saved preset sharing a slug
        // with a shipped one wins. That is what lets somebody customise
        // "Stockroom" and keep the name, with the shipped copy still intact
        // underneath and restored the moment theirs is deleted.
        foreach ([$this->shippedPresetDir($theme), $this->userPresetDir($theme)] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.json') as $path) {
                $meta = json_decode((string) file_get_contents($path), true);
                if (!is_array($meta)) {
                    continue;
                }
                $slug = basename($path, '.json');
                $meta['slug'] = $slug;
                $presets[$slug] = $meta;
            }
        }

        ksort($presets);

        return $presets;
    }

    public function getActivePreset(): ?string
    {
        return $this->get('identity.active_preset');
    }

    /**
     * Make a preset the live look. The outgoing preset's colors, fonts,
     * and options are snapshotted under its own slug (so switching back
     * restores edits); the incoming one is theme.json defaults, overlaid
     * with the preset file, overlaid with its saved snapshot.
     */
    public function applyPreset(string $slug): void
    {
        $presets = $this->getInstalledPresets();
        if (!isset($presets[$slug])) {
            throw new \InvalidArgumentException("Unknown preset [{$slug}].");
        }

        $theme = $this->getActiveTheme();
        $themeJson = json_decode((string) file_get_contents($this->getThemePath($theme) . '/theme.json'), true) ?: [];
        $old = $this->getActivePreset();

        \Illuminate\Support\Facades\DB::transaction(function () use ($slug, $presets, $themeJson, $theme, $old) {
            // Snapshot the outgoing look under its own slug
            if ($old !== null && $old !== $slug) {
                foreach (['colors', 'fonts', 'vars'] as $group) {
                    $live = Setting::where('group', $group)->pluck('value', 'key')->all();
                    foreach ($live as $key => $value) {
                        Setting::updateOrCreate(
                            ['group' => "{$group}__{$old}", 'key' => $key],
                            ['value' => $value]
                        );
                    }
                }
                $liveOptions = Setting::where('group', 'theme_settings')
                    ->where('key', 'like', "{$theme}_%")
                    ->pluck('value', 'key')->all();
                foreach ($liveOptions as $key => $value) {
                    Setting::updateOrCreate(
                        ['group' => "theme_settings__{$old}", 'key' => $key],
                        ['value' => $value]
                    );
                }
            }

            $preset = $presets[$slug];

            // Colors: base contract, preset overlay, then saved edits
            $colors = array_merge(
                $themeJson['colors'] ?? [],
                $preset['colors'] ?? [],
                Setting::where('group', "colors__{$slug}")->pluck('value', 'key')->all()
            );
            Setting::where('group', 'colors')->delete();
            foreach ($colors as $key => $value) {
                Setting::create(['group' => 'colors', 'key' => $key, 'value' => $value]);
            }

            // Fonts: same layering
            $fonts = array_merge(
                $themeJson['fonts'] ?? [],
                $preset['fonts'] ?? [],
                Setting::where('group', "fonts__{$slug}")->pluck('value', 'key')->all()
            );
            Setting::where('group', 'fonts')->delete();
            foreach ($fonts as $key => $value) {
                Setting::create(['group' => 'fonts', 'key' => $key, 'value' => $value]);
            }

            // Vars: non-color tokens (tracking, dims), same layering
            $vars = array_merge(
                $themeJson['vars'] ?? [],
                $preset['vars'] ?? [],
                Setting::where('group', "vars__{$slug}")->pluck('value', 'key')->all()
            );
            Setting::where('group', 'vars')->delete();
            foreach ($vars as $key => $value) {
                Setting::create(['group' => 'vars', 'key' => $key, 'value' => $value]);
            }

            // Options: schema defaults, preset overlay, then saved edits
            $options = [];
            foreach (($themeJson['settings'] ?? []) as $key => $schema) {
                $options[$key] = $schema['default'] ?? null;
            }
            $options = array_merge($options, $preset['options'] ?? []);
            $saved = Setting::where('group', "theme_settings__{$slug}")->pluck('value', 'key')->all();
            foreach ($options as $key => $value) {
                $storageKey = "{$theme}_{$key}";
                $value = $saved[$storageKey] ?? $value;
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                Setting::updateOrCreate(
                    ['group' => 'theme_settings', 'key' => $storageKey],
                    ['value' => $value]
                );
            }

            Setting::updateOrCreate(
                ['group' => 'identity', 'key' => 'active_preset'],
                ['value' => $slug]
            );

            // A template is a look that also brings its pages: the banner
            // groups it slides and the rows it arranges, the way a theme's
            // demo install does. A look without them leaves the pages alone.
            $this->applyPresetContent($preset);

            // A preset is the whole look, so page-type overrides do not survive
            // it (owner ruling 2026-08-20). The caller states which page types
            // will lose their own colours BEFORE calling this; clearing
            // deliberate work without naming it first is what would make
            // per-page overrides untrustworthy.
            foreach (array_keys(\App\Support\StorefrontPageType::TYPES) as $pageType) {
                foreach (['colors', 'fonts', 'vars', 'theme_settings'] as $family) {
                    Setting::where('group', $this->pageOverrideGroup($pageType, $family))->delete();
                }
            }
        });

        $this->clearCache();
    }

    /**
     * Install what a template brings besides its look: banner groups (by
     * slug, slides replaced) and page layouts (each page type it names is
     * rearranged whole, through the same save every editor uses).
     *
     * @param  array{banners?: array, layouts?: array}  $preset
     */
    /** @var list<callable(array): void> */
    private static array $presetContentInstallers = [];

    /**
     * A theme (or extension) that brings content of its own to a template,
     * such as Plaza's sliders, registers how to install it.
     */
    public static function onPresetContent(callable $installer): void
    {
        self::$presetContentInstallers[] = $installer;
    }

    public function applyPresetContent(array $preset): void
    {
        foreach (self::$presetContentInstallers as $installer) {
            $installer($preset);
        }

        foreach ((array) ($preset['banners'] ?? []) as $group) {
            if (! class_exists(\Extensions\Banners\Models\BannerGroup::class) || empty($group['slug'])) {
                continue;
            }
            $record = \Extensions\Banners\Models\BannerGroup::updateOrCreate(
                ['slug' => (string) $group['slug']],
                [
                    'name' => (string) ($group['name'] ?? $group['slug']),
                    'autoplay' => (bool) ($group['autoplay'] ?? true),
                    'speed' => (int) ($group['speed'] ?? 5000),
                    'height' => (int) ($group['height'] ?? 500),
                    'height_mobile' => (int) ($group['height_mobile'] ?? 400),
                    'full_width' => (bool) ($group['full_width'] ?? true),
                    'is_active' => true,
                ]
            );
            $record->slides()->delete();
            foreach (array_values((array) ($group['slides'] ?? [])) as $i => $slide) {
                $record->slides()->create([
                    'name' => (string) ($slide['name'] ?? ''),
                    'bg_image' => (string) ($slide['image'] ?? ''),
                    'bg_image_mobile' => $slide['image_mobile'] ?? null,
                    'bg_color' => (string) ($slide['bg_color'] ?? '#000000'),
                    'bg_size' => (string) ($slide['bg_size'] ?? 'cover'),
                    'link' => $slide['link'] ?? null,
                    'link_new_tab' => (bool) ($slide['new_tab'] ?? false),
                    'layers' => (array) ($slide['layers'] ?? []),
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }
        }

        foreach ((array) ($preset['layouts'] ?? []) as $pageType => $regions) {
            if ($pageType !== \App\Models\LayoutPlacement::EVERYWHERE && ! \App\Support\StorefrontPageType::exists((string) $pageType)) {
                continue;
            }
            $rows = [];
            foreach ((array) $regions as $region => $list) {
                if ($region === 'columns') {
                    continue;
                }
                foreach (\App\Models\LayoutTemplate::cleanRows($list) as $row) {
                    $rows[] = $row + ['id' => null, 'region' => (string) $region];
                }
            }
            app(LayoutSaver::class)->save((string) $pageType, [
                'columns' => (array) ($regions['columns'] ?? ['left' => 0, 'right' => 0]),
                'rows' => $rows,
            ]);
        }

        \App\Support\NavTree::forget();
    }

    /**
     * Capture the live look (colors, fonts, vars, and this theme's options)
     * as a new preset file. The preset dir is the only writable target and
     * the slug alphabet is closed, so a crafted name cannot escape it.
     */
    public function saveCurrentAsPreset(string $name, ?string $description = null): string
    {
        $slug = \Illuminate\Support\Str::slug($name);
        if ($slug === '') {
            throw new \InvalidArgumentException('The preset needs a name.');
        }

        $path = $this->presetPath($slug);
        if (file_exists($path)) {
            throw new \InvalidArgumentException("A preset named [{$slug}] already exists.");
        }

        $theme = $this->getActiveTheme();
        $options = [];
        foreach (Setting::where('group', 'theme_settings')->where('key', 'like', "{$theme}_%")->pluck('value', 'key') as $key => $value) {
            $options[substr($key, strlen($theme) + 1)] = $value;
        }

        $preset = [
            'name' => $name,
            'description' => $description ?: 'Saved from the live look on ' . now()->format('M j, Y') . '.',
            'colors' => Setting::where('group', 'colors')->orderBy('key')->pluck('value', 'key')->all(),
            'fonts' => Setting::where('group', 'fonts')->orderBy('key')->pluck('value', 'key')->all(),
            'vars' => Setting::where('group', 'vars')->orderBy('key')->pluck('value', 'key')->all(),
            'options' => $options,
        ];

        file_put_contents($path, json_encode($preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        return $slug;
    }

    public function deletePreset(string $slug): void
    {
        if ($slug === $this->getActivePreset()) {
            throw new \InvalidArgumentException('The active preset cannot be deleted. Switch to another look first.');
        }

        if ($this->isShippedPreset($slug)) {
            throw new \InvalidArgumentException("[{$slug}] is one of the looks Venta ships and cannot be deleted. Save your own version over it instead.");
        }

        $path = $this->presetPath($slug);
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Unknown preset [{$slug}].");
        }

        unlink($path);

        foreach (['colors', 'fonts', 'vars', 'theme_settings'] as $family) {
            Setting::where('group', "{$family}__{$slug}")->delete();
        }
    }

    /**
     * Validate and install an uploaded preset JSON. Only the known families
     * survive; anything else in the file is dropped rather than stored.
     */
    public function importPreset(string $json): string
    {
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('Not a preset file: it needs at least a name.');
        }
        if (empty($data['colors']) || !is_array($data['colors'])) {
            throw new \InvalidArgumentException('Not a preset file: it carries no colors.');
        }

        $slug = \Illuminate\Support\Str::slug($data['name']);
        if ($slug === '' || file_exists($this->presetPath($slug))) {
            throw new \InvalidArgumentException("A preset named [{$slug}] already exists.");
        }

        $clean = ['name' => $data['name']];
        $clean['description'] = is_string($data['description'] ?? null) ? $data['description'] : 'Imported preset.';
        foreach (['colors', 'fonts', 'vars', 'options'] as $family) {
            $values = $data[$family] ?? [];
            if (!is_array($values)) {
                continue;
            }
            $clean[$family] = collect($values)
                ->filter(fn ($v, $k) => is_string($k) && (is_scalar($v) || is_null($v)))
                ->all();
        }

        file_put_contents($this->presetPath($slug), json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        return $slug;
    }

    /**
     * Where a preset the MERCHANT saved lives.
     *
     * storage, never resources. resources/themes/{theme}/presets is a tracked
     * path and part of the deployment artifact: a preset written there would
     * be published to everyone who downloads Venta if anyone ran `git add -A`,
     * would be deleted by the next deploy because that folder is replaced from
     * git, and on a hosted install would be one tenant writing into the shared
     * image. storage is gitignored and persists, which is why the migrated
     * catalogue images already live there.
     *
     * Every writer (save, import, delete) goes through here, so the rule holds
     * in one place.
     */
    private function presetPath(string $slug, ?string $theme = null): string
    {
        $dir = $this->userPresetDir($theme);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . '/' . $this->validPresetSlug($slug) . '.json';
    }

    private function userPresetDir(?string $theme = null): string
    {
        return storage_path('app/themes/' . ($theme ?? $this->getActiveTheme()) . '/presets');
    }

    /** Where the presets Venta SHIPS live. Product content, read only. */
    private function shippedPresetDir(?string $theme = null): string
    {
        return $this->getThemePath($theme) . '/presets';
    }

    private function validPresetSlug(string $slug): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            throw new \InvalidArgumentException('Preset names use letters, numbers, and dashes.');
        }

        return $slug;
    }

    /** True when the slug names a preset Venta ships rather than one the merchant saved. */
    public function isShippedPreset(string $slug, ?string $theme = null): bool
    {
        return file_exists($this->shippedPresetDir($theme) . '/' . $this->validPresetSlug($slug) . '.json');
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
