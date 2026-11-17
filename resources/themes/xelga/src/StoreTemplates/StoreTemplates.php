<?php

namespace Themes\Xelga\StoreTemplates;

use Themes\Xelga\Catalog\Catalog;

/**
 * The starting stores shipped with the theme. Each is a folder in
 * store-templates/ holding store.json (its look, header, footer and home
 * page as portable documents) and its
 * photos. Portable means categories, products, brands and pages are named
 * by slug, so a store file works on any store (see Portable).
 *
 * A fresh install draws the default store, whose documents are the shipped
 * defaults in store-templates/_defaults.
 */
final class StoreTemplates
{
    public const DEFAULT = 'gadgets';

    public const FORMAT = 1;

    public const SLUG = '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/';

    /** The theme setting groups that make up a store's look; the rest (contact, cart) stay the merchant's. */
    public const LOOK_GROUPS = ['colors', 'typography', 'buttons', 'product-cards', 'layout'];

    /** The documents "Look and home page" replaces besides the theme settings. */
    public const SECTION_HANDLES = ['header', 'footer', 'home'];

    /** A photo a store file names: images/ and a plain file name. */
    public const IMAGE = '/\Aimages\/([a-z0-9][a-z0-9._-]{0,99}\.(?:jpe?g|png|webp))\z/';

    /** @var array<string, ?array> */
    private static array $memo = [];

    private static ?array $slugs = null;

    /**
     * Every store's slug, in the order the picker shows them.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        if (self::$slugs !== null) {
            return self::$slugs;
        }

        $found = [];
        foreach (glob(Catalog::root('store-templates/*/store.json')) ?: [] as $file) {
            $slug = basename(dirname($file));
            if (preg_match(self::SLUG, $slug) === 1 && ($store = self::get($slug)) !== null) {
                $found[$slug] = (int) ($store['position'] ?? 99);
            }
        }
        uksort($found, fn (string $a, string $b) => [$found[$a], $a] <=> [$found[$b], $b]);

        return self::$slugs = array_keys($found);
    }

    public static function exists(mixed $slug): bool
    {
        return is_string($slug) && in_array($slug, self::slugs(), true);
    }

    /** The decoded store file, or null when there is no such store or the file is broken. */
    public static function get(string $slug): ?array
    {
        if (array_key_exists($slug, self::$memo)) {
            return self::$memo[$slug];
        }
        if (preg_match(self::SLUG, $slug) !== 1) {
            return null;
        }

        $file = self::dir($slug) . '/store.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $valid = is_array($data)
            && ($data['format'] ?? null) === self::FORMAT
            && is_string($data['name'] ?? null)
            && is_array($data['documents'] ?? null)
            && is_array($data['documents']['settings'] ?? null);

        return self::$memo[$slug] = $valid ? $data : null;
    }

    /**
     * What the picker shows of each store.
     *
     * @return list<array{slug: string, name: string, line: string}>
     */
    public static function summaries(): array
    {
        return array_map(fn (string $slug) => [
            'slug' => $slug,
            'name' => (string) self::get($slug)['name'],
            'line' => (string) (self::get($slug)['line'] ?? ''),
        ], self::slugs());
    }

    public static function name(string $slug): string
    {
        return (string) (self::get($slug)['name'] ?? $slug);
    }

    public static function dir(string $slug): string
    {
        return Catalog::root('store-templates/' . $slug);
    }

    /** Where a store's photos go in the media library. */
    public static function mediaDir(string $slug): string
    {
        return 'media/xelga/' . $slug;
    }

    /**
     * The theme setting keys that belong to the look.
     *
     * @return list<string>
     */
    public static function lookKeys(): array
    {
        $keys = [];
        foreach (Catalog::themeSettingGroups() as $group) {
            if (! in_array($group['group'] ?? null, self::LOOK_GROUPS, true)) {
                continue;
            }
            foreach ((array) ($group['settings'] ?? []) as $setting) {
                if (($setting['type'] ?? null) !== 'scheme-colors' && is_string($setting['key'] ?? null)) {
                    $keys[] = $setting['key'];
                }
            }
        }

        return $keys;
    }

    /**
     * Which store a settings document says its look and its sections came
     * from; a store saved before starting stores existed came from the default.
     *
     * @return array{look: string, sections: string}
     */
    public static function basedOn(?array $settings): array
    {
        $based = (array) ($settings['based_on'] ?? []);

        return [
            'look' => self::exists($based['look'] ?? null) ? $based['look'] : self::DEFAULT,
            'sections' => self::exists($based['sections'] ?? null) ? $based['sections'] : self::DEFAULT,
        ];
    }

    public static function flush(): void
    {
        self::$memo = [];
        self::$slugs = null;
    }
}
