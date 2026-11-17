<?php

namespace Themes\Xelga\Catalog;

/**
 * The one reader of the theme's schema files: section types, theme setting
 * groups and font pairs. Forms, validation and rendering all read this, so
 * adding a section type means adding a schema file and a view, nothing else.
 *
 * The compiled result is kept in bootstrap/cache, keyed by the theme version
 * and, while app.debug is on, by the newest schema file time. In production
 * a theme update changes the version, which is what rebuilds it.
 */
final class Catalog
{
    /** Setting types a schema may use. */
    public const TYPES = [
        'text', 'textarea', 'richtext', 'image', 'url', 'select', 'range', 'toggle',
        'color-scheme', 'menu-picker', 'scheme-colors',
        'product-picker', 'category-picker', 'brand-picker', 'page-picker', 'blog-post-picker',
    ];

    /** Types whose live preview needs no server: text is written straight into the page. */
    private const TEXT_TYPES = ['text', 'textarea', 'richtext'];

    /** Types a preview can only show after the server draws the section again. */
    private const RENDER_TYPES = ['menu-picker', 'product-picker', 'category-picker', 'brand-picker', 'page-picker', 'blog-post-picker'];

    private static ?array $compiled = null;

    public static function root(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);

        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }

    /** Drop the memo, so the next read compiles again (tests, the linter). */
    public static function flush(): void
    {
        self::$compiled = null;
    }

    /** @return array{version: string, sections: array<string, array>, theme_settings: list<array>, font_pairs: list<array>} */
    public static function all(): array
    {
        if (self::$compiled !== null) {
            return self::$compiled;
        }

        $key = self::cacheKey();
        $file = self::cacheFile();

        if (is_file($file)) {
            $cached = @include $file;
            if (is_array($cached) && ($cached['key'] ?? null) === $key) {
                return self::$compiled = $cached['data'];
            }
        }

        $data = self::compile();

        $dir = dirname($file);
        if (is_dir($dir) && is_writable($dir)) {
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, '<?php return ' . var_export(['key' => $key, 'data' => $data], true) . ';' . PHP_EOL) !== false) {
                @rename($tmp, $file);
            }
            @unlink($tmp);
        }

        return self::$compiled = $data;
    }

    /** One section type, or null when the catalog has no such type. */
    public static function section(string $type): ?SectionSchema
    {
        $spec = self::all()['sections'][$type] ?? null;

        return $spec === null ? null : new SectionSchema($spec);
    }

    /** @return array<string, array> */
    public static function sections(): array
    {
        return self::all()['sections'];
    }

    /** @return list<array> the theme settings groups */
    public static function themeSettingGroups(): array
    {
        return self::all()['theme_settings'];
    }

    /** Every theme setting by key, across groups. @return array<string, SettingSchema> */
    public static function themeSettings(): array
    {
        $out = [];
        foreach (self::themeSettingGroups() as $group) {
            foreach ($group['settings'] as $setting) {
                $out[$setting['key']] = new SettingSchema($setting);
            }
        }

        return $out;
    }

    /** @return list<array> */
    public static function fontPairs(): array
    {
        return self::all()['font_pairs'];
    }

    public static function fontPair(string $value): ?array
    {
        foreach (self::fontPairs() as $pair) {
            if ($pair['value'] === $value) {
                return $pair;
            }
        }

        return null;
    }

    /** The scheme-colors setting (roles, contrast checks, limits). */
    public static function schemeSetting(): SettingSchema
    {
        foreach (self::themeSettings() as $setting) {
            if ($setting->type === 'scheme-colors') {
                return $setting;
            }
        }

        throw new \LogicException('theme-settings.json has no scheme-colors setting.');
    }

    /** @return list<string> */
    public static function schemeRoles(): array
    {
        return array_column(self::schemeSetting()->raw['roles'] ?? [], 'role');
    }

    /**
     * How the preview applies a change to this setting: text, attr, css or
     * render. Explicit `live` wins; otherwise inferred from the type.
     */
    public static function liveMode(array $setting, array $renderKeys = []): string
    {
        if (isset($setting['live']) && in_array($setting['live'], ['text', 'attr', 'css', 'render'], true)) {
            return $setting['live'];
        }
        if (in_array($setting['key'] ?? '', $renderKeys, true) || in_array($setting['type'] ?? '', self::RENDER_TYPES, true)) {
            return 'render';
        }
        if (in_array($setting['type'] ?? '', self::TEXT_TYPES, true)) {
            return 'text';
        }

        return 'attr';
    }

    private static function compile(): array
    {
        $sections = [];
        foreach (self::schemaFiles() as $file) {
            $spec = json_decode((string) file_get_contents($file), true);
            if (! is_array($spec) || empty($spec['type'])) {
                continue;
            }
            $renderKeys = (array) ($spec['render_keys'] ?? []);
            $spec['settings'] = array_map(fn (array $s) => $s + ['live' => self::liveMode($s, $renderKeys)], (array) ($spec['settings'] ?? []));
            $blocks = (array) ($spec['blocks'] ?? []);
            $blocks += ['min' => 0, 'max' => 0, 'types' => []];
            $blocks['types'] = array_map(function (array $type) use ($renderKeys) {
                $type['settings'] = array_map(fn (array $s) => $s + ['live' => self::liveMode($s, $renderKeys)], (array) ($type['settings'] ?? []));

                return $type + ['limit' => null];
            }, (array) $blocks['types']);
            $spec['blocks'] = $blocks;
            $spec += ['templates' => [], 'limit' => null, 'main' => false, 'variants' => [], 'presets' => [], 'group' => 'template'];
            $sections[$spec['type']] = $spec;
        }
        ksort($sections);

        $settings = json_decode((string) @file_get_contents(self::root('schemas/theme-settings.json')), true);
        $groups = array_map(function (array $group) {
            $group['settings'] = array_map(fn (array $s) => $s + ['live' => 'css'], (array) ($group['settings'] ?? []));

            return $group;
        }, (array) ($settings['groups'] ?? []));

        $pairs = json_decode((string) @file_get_contents(self::root('schemas/font-pairs.json')), true);

        return [
            'version' => self::themeVersion(),
            'sections' => $sections,
            'theme_settings' => $groups,
            'font_pairs' => array_values((array) ($pairs['pairs'] ?? [])),
        ];
    }

    /** @return list<string> */
    public static function schemaFiles(): array
    {
        $files = glob(self::root('schemas/sections/*.json')) ?: [];
        sort($files);

        return $files;
    }

    public static function themeVersion(): string
    {
        $meta = json_decode((string) @file_get_contents(self::root('theme.json')), true);

        return (string) ($meta['version'] ?? '0');
    }

    private static function cacheKey(): string
    {
        $key = self::themeVersion();
        if (config('app.debug')) {
            $times = array_map('filemtime', array_merge(
                self::schemaFiles(),
                array_filter([self::root('schemas/theme-settings.json'), self::root('schemas/font-pairs.json')], 'is_file'),
            ));
            $key .= '@' . ($times === [] ? 0 : max($times)) . '@' . count($times);
        }

        return $key;
    }

    private static function cacheFile(): string
    {
        return app()->bootstrapPath('cache/xelga-catalog.php');
    }
}
