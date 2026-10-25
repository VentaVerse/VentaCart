<?php

namespace App\Support;

/**
 * A placement's settings, kept to what its widget declares.
 *
 * A widget's settings print into its view: into attributes, inline styles
 * and Alpine expressions as well as text. Escaping covers text, not a
 * number that turns out to be script inside x-data. So the settings a
 * layout saves, and those a template, preset or theme file installs, keep
 * only the keys the widget's schema names, each in the shape its field type
 * allows:
 *
 *   select          one of its listed options (or a short plain value when
 *                   the options are looked up at edit time, such as a
 *                   category); a list of them when 'multiple'
 *   number          a number, clamped to the field's min and max
 *   toggle,
 *   checkbox        true or false
 *   url, link       a safe link (SafeInput::link); a text field named url,
 *                   link, href or *_url is treated as one too
 *   color           a hex colour or a palette name ("primary")
 *   image, media    a storage path or an http(s) address; 'images' a list
 *   rows, list      a list of rows, each cleaned by the field's 'fields'
 *   textarea        text with line breaks; a 'code' field (the HTML block)
 *                   is kept as written, since only storefront.code holders
 *                   may change one (LayoutSaver checks)
 *   text and the rest   plain text, capped
 *
 * A field type this class does not know (a theme's own) keeps a bounded
 * copy of plain data: short keys, capped text, numbers and booleans, no
 * deeper than a few levels. That theme still cleans it for its own use.
 * A widget nothing registers keeps nothing.
 */
final class WidgetConfig
{
    public const TEXT_MAX = 2000;

    public const TEXTAREA_MAX = 20000;

    public const CODE_MAX = 200000;

    public const LIST_MAX = 100;

    private const NUMBER_LIMIT = 1000000;

    private const DEPTH_MAX = 4;

    /**
     * @return array<string, mixed>
     */
    public static function clean(string $widget, mixed $config): array
    {
        $spec = Widgets::get($widget);

        if ($spec === null || ! is_array($config)) {
            return [];
        }

        return self::fields((array) ($spec['schema'] ?? []), $config);
    }

    /**
     * The widget's fields marked 'code': printed on the storefront as
     * written, so only a storefront.code holder may set one.
     *
     * @return list<string>
     */
    public static function codeFields(string $widget): array
    {
        return array_values(array_keys(array_filter(
            (array) (Widgets::get($widget)['schema'] ?? []),
            fn ($field) => is_array($field) && ($field['code'] ?? false) === true
        )));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function fields(array $schema, array $values, int $depth = 0): array
    {
        $out = [];

        foreach ($schema as $name => $field) {
            if (! is_string($name) || ! is_array($field) || ! array_key_exists($name, $values)) {
                continue;
            }

            $value = self::field($name, $field, $values[$name], $depth);
            if ($value !== null) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /** One value in its field's shape, or null to leave it out. */
    private static function field(string $name, array $field, mixed $value, int $depth): mixed
    {
        $type = is_string($field['type'] ?? null) ? $field['type'] : 'text';

        if ($value === null) {
            return null;
        }

        return match ($type) {
            'select' => self::select($field, $value),
            'number', 'range' => self::number($field, $value),
            'toggle', 'checkbox', 'boolean', 'switch' => self::boolean($value),
            'url', 'link' => SafeInput::link($value),
            'color', 'colour' => self::color($value),
            'image', 'media' => self::media($value),
            'images' => is_array($value) ? array_values(array_filter(array_map(self::media(...), array_slice(array_values($value), 0, self::LIST_MAX)), fn ($v) => $v !== '')) : null,
            'rows', 'list' => self::rows($field, $value, $depth),
            'textarea' => ($field['code'] ?? false) === true
                ? (is_string($value) ? substr($value, 0, self::CODE_MAX) : null)
                : SafeInput::text($value, (int) ($field['max'] ?? self::TEXTAREA_MAX), true),
            'text', 'email', 'tel', 'date', 'datetime', 'time' => self::looksLikeLink($name)
                ? SafeInput::link($value)
                : SafeInput::text($value, (int) ($field['max'] ?? self::TEXT_MAX)),
            default => self::plain($value, $depth),
        };
    }

    private static function looksLikeLink(string $name): bool
    {
        return in_array($name, ['url', 'link', 'href'], true) || str_ends_with($name, '_url');
    }

    private static function select(array $field, mixed $value): mixed
    {
        $options = $field['options'] ?? null;

        if (($field['multiple'] ?? false) === true) {
            if (! is_array($value)) {
                return null;
            }
            $picked = [];
            foreach (array_slice(array_values($value), 0, self::LIST_MAX) as $one) {
                $clean = self::option($options, $one);
                if ($clean !== null && $clean !== '') {
                    $picked[] = $clean;
                }
            }

            return array_values(array_unique($picked));
        }

        return self::option($options, $value);
    }

    /** A listed option's value, '' for "none chosen", or null. */
    private static function option(mixed $options, mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }
        if ((string) $value === '') {
            return '';
        }

        // Options looked up when the editor opens (the store's categories,
        // menus, banner groups) are not queried on every save; the value
        // only has to be a short plain one, and the widget's resolver looks
        // it up.
        if (! is_array($options)) {
            $text = SafeInput::text($value, 255);

            return preg_match('/^[\w\-. ]+$/u', $text) === 1 ? $text : null;
        }

        $allowed = [];
        foreach ($options as $key => $label) {
            $allowed[] = $key;
            // An option group: its own options are choices too.
            if (is_array($label)) {
                array_push($allowed, ...array_keys($label));
            }
        }

        return SafeInput::oneOf($value, $allowed);
    }

    private static function number(array $field, mixed $value): int|float|null
    {
        $min = is_numeric($field['min'] ?? null) ? (float) $field['min'] : -self::NUMBER_LIMIT;
        $max = is_numeric($field['max'] ?? null) ? (float) $field['max'] : self::NUMBER_LIMIT;

        return SafeInput::number($value, $min, max($min, $max));
    }

    private static function boolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (! is_scalar($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private static function color(mixed $value): ?string
    {
        if ($value === '') {
            return '';
        }
        $hex = SafeInput::color($value);
        if ($hex !== '') {
            return $hex;
        }

        // A palette name the theme maps to its own colour.
        return is_string($value) && preg_match('/^[a-z][a-z0-9-]{0,39}$/', $value) === 1 ? $value : null;
    }

    private static function media(mixed $value): string
    {
        $path = SafeInput::storagePath($value);
        if ($path !== '') {
            return $path;
        }

        $link = SafeInput::link($value);

        return preg_match('#^https?://#i', $link) === 1 ? $link : '';
    }

    private static function rows(array $field, mixed $value, int $depth): ?array
    {
        if (! is_array($value) || $depth >= self::DEPTH_MAX) {
            return null;
        }

        $fields = is_array($field['fields'] ?? null) ? $field['fields'] : null;
        $out = [];

        foreach (array_slice(array_values($value), 0, self::LIST_MAX) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = $fields !== null ? self::fields($fields, $row, $depth + 1) : (array) self::plain($row, $depth + 1);
        }

        return $out;
    }

    /** Plain data, bounded: for a field type a theme defines for itself. */
    private static function plain(mixed $value, int $depth): mixed
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return SafeInput::number($value, -self::NUMBER_LIMIT, self::NUMBER_LIMIT);
        }
        if (is_string($value)) {
            return SafeInput::text($value, self::TEXTAREA_MAX, true);
        }
        if (! is_array($value) || $depth >= self::DEPTH_MAX) {
            return null;
        }

        $out = [];
        foreach (array_slice($value, 0, self::LIST_MAX, true) as $key => $item) {
            if (! is_int($key) && (! is_string($key) || preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $key) !== 1)) {
                continue;
            }
            $clean = self::plain($item, $depth + 1);
            if ($clean !== null) {
                $out[$key] = $clean;
            }
        }

        return $out;
    }
}
