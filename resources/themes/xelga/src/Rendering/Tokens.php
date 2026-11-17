<?php

namespace Themes\Xelga\Rendering;

use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\SettingSchema;

/**
 * The tokens stylesheet: custom properties from the theme settings, the
 * chosen font pair's lettering, and one rule per color scheme. Every value
 * printed here comes from a schema map, a bounded number or a checked hex
 * color, never from text a merchant typed.
 */
final class Tokens
{
    /** A token value may only use these characters. */
    private const SAFE_VALUE = '/^[a-zA-Z0-9 #.,%()\/"\'+\-]{1,200}$/';

    public static function css(array $settings): string
    {
        $values = (array) ($settings['values'] ?? []);
        $root = [];
        $schema = Catalog::themeSettings();

        $pairValue = (string) ($values['font_pair'] ?? ($schema['font_pair']?->default ?? ''));
        $pair = Catalog::fontPair($pairValue) ?? (Catalog::fontPairs()[0] ?? null);
        if ($pair !== null) {
            $root['--font-heading'] = (string) $pair['heading'];
            $root['--font-body'] = (string) $pair['body'];
            if (isset($pair['heading_weight'])) {
                $root['--font-heading-weight'] = (string) (int) $pair['heading_weight'];
            }
            foreach ((array) ($pair['tokens'] ?? []) as $name => $value) {
                $root[(string) $name] = (string) $value;
            }
        }

        foreach ($schema as $key => $setting) {
            $target = (array) ($setting->raw['target'] ?? []);
            if (($target['type'] ?? null) !== 'token' || empty($target['name'])) {
                continue;
            }
            // Only what the merchant set overrides the pair; the rest keeps
            // the setting's default.
            $value = array_key_exists($key, $values) ? $values[$key] : $setting->default;
            $css = self::tokenValue($setting, $target, $value);
            if ($css !== null) {
                $root[(string) $target['name']] = $css;
            }
        }
        // The pair's own heading weight wins until the merchant picks one.
        if ($pair !== null && isset($pair['heading_weight']) && ! array_key_exists('heading_weight', $values)) {
            $root['--font-heading-weight'] = (string) (int) $pair['heading_weight'];
        }

        $out = ':root{' . self::declarations($root) . '}';

        $roles = Catalog::schemeRoles();
        foreach ((array) ($settings['schemes'] ?? []) as $scheme) {
            $id = (string) ($scheme['id'] ?? '');
            if (preg_match('/^scheme-[1-9][0-9]?$/', $id) !== 1) {
                continue;
            }
            $props = [];
            foreach ($roles as $role) {
                $color = (string) ($scheme['colors'][$role] ?? '');
                if (preg_match('/^#[0-9a-f]{6}$/i', $color) === 1) {
                    $props['--c-' . $role] = strtolower($color);
                }
            }
            $selector = $id === 'scheme-1' ? ':root,.scheme-1' : '.' . $id;
            $out .= "\n{$selector}{" . self::declarations($props) . '}';
        }

        return $out . "\n";
    }

    /** @param array<string, string> $props */
    private static function declarations(array $props): string
    {
        $out = [];
        foreach ($props as $name => $value) {
            if (preg_match('/^--[a-z0-9-]{1,40}$/', $name) !== 1 || preg_match(self::SAFE_VALUE, $value) !== 1 || str_contains($value, '/*')) {
                continue;
            }
            $out[] = $name . ':' . $value;
        }

        return implode(';', $out);
    }

    private static function tokenValue(SettingSchema $setting, array $target, mixed $value): ?string
    {
        if (isset($target['map']) && is_array($target['map'])) {
            $key = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

            return isset($target['map'][$key]) ? (string) $target['map'][$key] : null;
        }
        if ($setting->type === 'range') {
            if (! is_int($value) && ! is_float($value)) {
                return null;
            }

            $n = round((float) $value, 2);
            $text = floor($n) === $n ? (string) (int) $n : rtrim(number_format($n, 2, '.', ''), '0');

            return $text . (string) ($target['unit'] ?? '');
        }
        if ($setting->type === 'select') {
            return in_array((string) $value, $setting->optionValues(), true) ? (string) $value . (string) ($target['unit'] ?? '') : null;
        }

        return null;
    }
}
