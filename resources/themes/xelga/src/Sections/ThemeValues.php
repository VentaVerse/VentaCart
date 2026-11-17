<?php

namespace Themes\Xelga\Sections;

use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\SettingSchema;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Rendering\DocumentRepository;

/**
 * The theme settings as the views read them: every value the store set,
 * over each setting's default. The draft in the editor's preview, the
 * published settings for shoppers.
 *
 * Settings that only change how things look reach the page as attributes on
 * <html> (data-card-style="panel"), so the stylesheet does the work and the
 * editor can change them live without drawing anything again.
 */
final class ThemeValues
{
    /** @return array<string, mixed> */
    public static function all(): array
    {
        $stored = (array) (app(DocumentRepository::class)->content(Handles::SETTINGS)['values'] ?? []);
        $out = [];
        foreach (Catalog::themeSettings() as $key => $setting) {
            if ($setting->type === 'scheme-colors') {
                continue;
            }
            $out[$key] = array_key_exists($key, $stored) ? $stored[$key] : $setting->default;
        }

        return $out;
    }

    public static function get(string $key, mixed $fallback = null): mixed
    {
        return self::all()[$key] ?? $fallback;
    }

    /**
     * The attributes the layout prints on <html>: one per setting whose
     * target is an attribute, its value checked against the setting's own
     * choices so nothing typed reaches the markup.
     *
     * @return array<string, string>
     */
    public static function htmlAttributes(): array
    {
        $values = self::all();
        $out = [];
        foreach (Catalog::themeSettings() as $key => $setting) {
            $target = (array) ($setting->raw['target'] ?? []);
            $name = (string) ($target['name'] ?? '');
            if (($target['type'] ?? null) !== 'attr' || preg_match('/^data-[a-z0-9-]{1,40}$/', $name) !== 1) {
                continue;
            }
            $value = self::attributeValue($setting, $values[$key] ?? $setting->default);
            if ($value !== null) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    private static function attributeValue(SettingSchema $setting, mixed $value): ?string
    {
        if ($setting->type === 'toggle') {
            return ($value === true || ($value === null && $setting->default === true)) ? 'true' : 'false';
        }
        if ($setting->type === 'select') {
            $options = $setting->optionValues();

            return in_array((string) $value, $options, true) ? (string) $value : (in_array((string) $setting->default, $options, true) ? (string) $setting->default : null);
        }

        return null;
    }
}
