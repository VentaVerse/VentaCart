<?php

namespace App\Support;

use App\Services\ThemeService;

/**
 * The active theme's own settings, resolved with their theme.json defaults,
 * once per request. Any view of any theme reads one the same way, whether
 * it renders before the layout (a page's slot does) or inside it.
 */
final class ThemeOptions
{
    /** @var array<string, mixed>|null */
    private static ?array $resolved = null;

    private static ?string $for = null;

    /** Forget the per-request memo, after settings were overlaid. */
    public static function flush(): void
    {
        self::$resolved = null;
        self::$for = null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $service = app(ThemeService::class);
        $theme = $service->getActiveTheme();

        if (self::$resolved !== null && self::$for === $theme) {
            return self::$resolved;
        }

        $out = [];
        foreach ($service->getThemeSettingsSchema($theme) as $key => $schema) {
            $out[$key] = $service->getThemeSetting($key, $schema['default'] ?? null);
        }

        self::$for = $theme;

        return self::$resolved = $out;
    }

    /**
     * The choices of a select or layout option as value => label. A manifest
     * may list bare values (`["grid", "list"]`) or label them
     * (`{"grid": "Grid", "list": "List"}`); either way the form sends the
     * value and shows the label.
     *
     * @param  callable(string): string|null  $label  how to label a bare value
     * @return array<string, string>
     */
    public static function choices(mixed $options, ?callable $label = null): array
    {
        if (! is_array($options)) {
            return [];
        }

        $label ??= fn (string $value) => ucfirst($value);
        $out = [];

        foreach ($options as $key => $text) {
            if (! is_scalar($text)) {
                continue;
            }
            if (array_is_list($options)) {
                $out[(string) $text] = $label((string) $text);
            } else {
                $out[(string) $key] = (string) $text;
            }
        }

        return $out;
    }

    public static function forget(): void
    {
        self::$resolved = null;
        self::$for = null;
    }
}
