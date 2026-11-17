<?php

namespace Themes\Xelga\Catalog;

use Themes\Xelga\Extensions\ExtensionParts;
use Themes\Xelga\Extensions\Extensions;
use Themes\Xelga\Extensions\Registrar;
use Themes\Xelga\Rendering\SectionData;

/**
 * The one reader of the theme's schema files: section types, theme setting
 * groups and font pairs. Forms, validation and rendering all read this, so
 * adding a section type means adding a schema file and a view, nothing else.
 *
 * The compiled result is kept in bootstrap/cache, keyed by the theme version,
 * the registered extensions and their versions, and, while app.debug is on,
 * by the newest schema file time. In production a theme or extension update
 * changes a version, and switching an extension changes the list, which is
 * what rebuilds it.
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

    /** The extension fingerprint the memo was compiled for. */
    private static ?string $compiledFor = null;

    /** An extension section type: the extension's name, a dot, the section's own name. */
    public const EXTENSION_TYPE = '/^([a-z][a-z0-9-]{0,39})\.([a-z0-9]+(?:-[a-z0-9]+)*)$/';

    public static function root(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);

        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }

    /** Drop the memo, so the next read compiles again (tests, the linter). */
    public static function flush(): void
    {
        self::$compiled = null;
        self::$compiledFor = null;
    }

    /** @return array{version: string, sections: array<string, array>, theme_settings: list<array>, font_pairs: list<array>} */
    public static function all(): array
    {
        $fingerprint = self::extensions()->fingerprint();
        if (self::$compiled !== null && self::$compiledFor === $fingerprint) {
            return self::$compiled;
        }
        self::$compiledFor = $fingerprint;

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
            $sections[$spec['type']] = self::compileSection($spec);
        }
        ksort($sections);

        // Extension sections come after the theme's own, never in their
        // place: a type is always its extension's name, a dot and a name.
        foreach (self::extensions()->all() as $part) {
            $own = [];
            foreach ($part->schemaFiles() as $file) {
                $spec = self::extensionSection($part, $file);
                if ($spec !== null) {
                    $own[$spec['type']] = self::compileSection($spec);
                }
            }
            ksort($own);
            $sections += $own;
        }

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

    private static function compileSection(array $spec): array
    {
        $renderKeys = (array) ($spec['render_keys'] ?? []);
        $spec['settings'] = array_map(fn (array $s) => $s + ['live' => self::liveMode($s, $renderKeys)], (array) ($spec['settings'] ?? []));
        $blocks = (array) ($spec['blocks'] ?? []);
        $blocks += ['min' => 0, 'max' => 0, 'types' => []];
        $blocks['types'] = array_map(function (array $type) use ($renderKeys) {
            $type['settings'] = array_map(fn (array $s) => $s + ['live' => self::liveMode($s, $renderKeys)], (array) ($type['settings'] ?? []));

            return $type + ['limit' => null];
        }, (array) $blocks['types']);
        $spec['blocks'] = $blocks;

        return $spec + ['templates' => [], 'limit' => null, 'main' => false, 'variants' => [], 'presets' => [], 'group' => 'template'];
    }

    /**
     * One extension schema file, checked and stamped with where it comes
     * from, or null (logged) when it is not one Xelga accepts.
     */
    public static function extensionSection(ExtensionParts $part, string $file, ?callable $report = null): ?array
    {
        $name = basename($file, '.json');
        $spec = json_decode((string) file_get_contents($file), true);
        $refuse = function (string $reason) use ($part, $name, $report): ?array {
            $report === null ? Registrar::refuse($part->name, "the section {$name}: {$reason}") : $report($reason);

            return null;
        };

        if (! is_array($spec) || array_is_list($spec)) {
            return $refuse('the schema is not a JSON object');
        }
        if (preg_match(self::EXTENSION_TYPE, (string) ($spec['type'] ?? ''), $m) !== 1 || $m[1] !== $part->name || $m[2] !== $name || strlen($name) > 40) {
            return $refuse("its type must be {$part->name}.{$name}, named after its file");
        }
        if (($spec['group'] ?? 'template') !== 'template') {
            return $refuse('extension sections go in page templates only');
        }
        $settings = [...(array) ($spec['settings'] ?? [])];
        foreach ((array) ($spec['blocks']['types'] ?? []) as $type) {
            $settings = [...$settings, ...(array) (is_array($type) ? ($type['settings'] ?? []) : [])];
        }
        foreach ($settings as $setting) {
            $type = is_array($setting) ? ($setting['type'] ?? null) : null;
            if (! in_array($type, self::TYPES, true) || $type === 'scheme-colors') {
                return $refuse('a setting has a type extension sections cannot use');
            }
        }
        $class = $spec['data_class'] ?? null;
        if ($class !== null) {
            if (! is_string($class) || $part->namespace === '' || ! str_starts_with(ltrim($class, '\\'), $part->namespace)) {
                return $refuse('its data class must be in the extension\'s own namespace');
            }
            $class = ltrim($class, '\\');
            if (! class_exists($class) || ! is_subclass_of($class, SectionData::class)) {
                return $refuse('its data class must exist and implement ' . SectionData::class);
            }
        }

        $spec['group'] = 'template';
        $spec['source'] = $part->name;
        $spec['source_label'] = $part->title;
        $spec['view'] = $part->viewNamespace() . '::sections.' . $name;
        $spec['data_class'] = $class;
        $spec['class'] = self::extensionClass($spec['type']);

        return $spec;
    }

    /** The CSS class of an extension section: `s-{extension}--{name}`, which no theme section uses. */
    public static function extensionClass(string $type): string
    {
        return 's-' . str_replace('.', '--', $type);
    }

    /** The extension parts of this request. */
    public static function extensions(): Extensions
    {
        return Extensions::current();
    }

    /** @return list<string> every registered extension's schema files */
    public static function extensionSchemaFiles(): array
    {
        $files = [];
        foreach (self::extensions()->all() as $part) {
            $files = [...$files, ...$part->schemaFiles()];
        }

        return $files;
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
            foreach (self::extensions()->all() as $part) {
                $key .= '@' . $part->name . ':' . $part->fileStamp();
            }
        }

        return $key . '|' . self::extensions()->fingerprint();
    }

    private static function cacheFile(): string
    {
        return app()->bootstrapPath('cache/xelga-catalog.php');
    }
}
