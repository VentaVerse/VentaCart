<?php

namespace Themes\Xelga\StoreTemplates;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\SettingSchema;

/**
 * Documents in a store file name categories, products, brands and pages by
 * slug, and their photos as images/{file}; a store keeps ids and media
 * library paths. This turns one into the other.
 *
 *   picker setting   "light-roast" or ["benguet-highland-washed", ...]
 *   link             {"type": "category", "slug": "light-roast"}
 *   photo            "images/slide-1.jpg"
 *
 * A slug this store does not have leaves the setting empty, so the section
 * shows its empty state in the editor until the merchant picks something.
 * Records are read through core's models: in the admin every product is
 * visible, as it is to the pickers.
 */
final class Portable
{
    /** The record each picker type stores. */
    public const RECORDS = [
        'category-picker' => 'category',
        'product-picker' => 'product',
        'brand-picker' => 'brand',
        'page-picker' => 'page',
    ];

    /** Link types that point at one record. */
    public const LINK_RECORDS = ['category', 'product', 'brand', 'page'];

    public const SLUG = '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/';

    /** @var \Closure(string, list<string>): array<string, int> */
    private \Closure $finder;

    /**
     * @param  string  $store  the store's slug, for its photo paths
     * @param  (\Closure(string, list<string>): array<string, int>)|null  $finder  slug => id per record; core's models when null
     */
    public function __construct(private readonly string $store, ?\Closure $finder = null)
    {
        $this->finder = $finder ?? fn (string $record, array $slugs) => self::find($record, $slugs);
    }

    /**
     * The document with slugs turned into this store's ids and photos into
     * media library paths, and every reference this store could not fill.
     *
     * @return array{content: array, missing: list<array{section: string, block: ?string, key: string, record: string, slug: string}>}
     */
    public function resolve(array $content): array
    {
        $wanted = [];
        self::walk($content, function (SettingSchema $setting, mixed $value) use (&$wanted) {
            foreach (self::slugsIn($setting, $value) as [$record, $slug]) {
                $wanted[$record][$slug] = true;
            }

            return $value;
        });

        $ids = [];
        foreach ($wanted as $record => $slugs) {
            $ids[$record] = ($this->finder)($record, array_keys($slugs));
        }

        $missing = [];
        $content = self::walk($content, function (SettingSchema $setting, mixed $value, string $section, ?string $block) use ($ids, &$missing) {
            $note = function (string $record, string $slug) use ($setting, $section, $block, &$missing) {
                $missing[] = ['section' => $section, 'block' => $block, 'key' => $setting->key, 'record' => $record, 'slug' => $slug];
            };

            if ($setting->type === 'image') {
                return $this->photo($value);
            }
            if ($setting->type === 'url') {
                if (! is_array($value) || ! in_array($value['type'] ?? null, self::LINK_RECORDS, true) || ! is_string($value['slug'] ?? null)) {
                    return $value;
                }
                $id = $ids[$value['type']][$value['slug']] ?? null;
                if ($id === null) {
                    $note($value['type'], $value['slug']);

                    return null;
                }

                return ['type' => $value['type'], 'id' => $id];
            }

            $record = self::RECORDS[$setting->type] ?? null;
            if ($record === null) {
                return $value;
            }
            if ($setting->multiple()) {
                $out = [];
                foreach (is_array($value) ? $value : [] as $slug) {
                    if (! is_string($slug)) {
                        continue;
                    }
                    $id = $ids[$record][$slug] ?? null;
                    if ($id === null) {
                        $note($record, $slug);
                    } else {
                        $out[] = $id;
                    }
                }

                return $out;
            }
            if (! is_string($value) || $value === '') {
                return null;
            }
            $id = $ids[$record][$value] ?? null;
            if ($id === null) {
                $note($record, $value);
            }

            return $id;
        });

        return ['content' => $content, 'missing' => $missing];
    }

    /**
     * Every portable reference in a document, for the linter.
     *
     * @return list<array{0: string, 1: string}> [record, slug]
     */
    public static function references(array $content): array
    {
        $out = [];
        self::walk($content, function (SettingSchema $setting, mixed $value) use (&$out) {
            array_push($out, ...self::slugsIn($setting, $value));

            return $value;
        });

        return $out;
    }

    /**
     * Every photo a document names, as given.
     *
     * @return list<string>
     */
    public static function photos(array $content): array
    {
        $out = [];
        self::walk($content, function (SettingSchema $setting, mixed $value) use (&$out) {
            if ($setting->type === 'image' && is_string($value) && $value !== '') {
                $out[] = $value;
            }

            return $value;
        });

        return $out;
    }

    /** A photo named images/{file} becomes that file in this store's media folder. */
    public function photo(mixed $value): mixed
    {
        if (is_string($value) && preg_match(StoreTemplates::IMAGE, $value, $m) === 1) {
            return StoreTemplates::mediaDir($this->store) . '/' . $m[1];
        }

        return $value;
    }

    /**
     * Calls $fn for every setting value of every section and block a
     * schema knows, and keeps what it returns.
     *
     * @param  callable(SettingSchema, mixed, string, ?string): mixed  $fn
     */
    private static function walk(array $content, callable $fn): array
    {
        foreach ((array) ($content['sections'] ?? []) as $id => $section) {
            $schema = is_array($section) ? Catalog::section((string) ($section['type'] ?? '')) : null;
            if ($schema === null) {
                continue;
            }
            $settings = $schema->settings();
            foreach ((array) ($section['settings'] ?? []) as $key => $value) {
                if (isset($settings[$key])) {
                    $content['sections'][$id]['settings'][$key] = $fn($settings[$key], $value, (string) $id, null);
                }
            }
            foreach ((array) ($section['blocks'] ?? []) as $blockId => $block) {
                $blockSettings = is_array($block) ? $schema->blockSettings((string) ($block['type'] ?? '')) : [];
                foreach ((array) ($block['settings'] ?? []) as $key => $value) {
                    if (isset($blockSettings[$key])) {
                        $content['sections'][$id]['blocks'][$blockId]['settings'][$key] = $fn($blockSettings[$key], $value, (string) $id, (string) $blockId);
                    }
                }
            }
        }

        return $content;
    }

    /** @return list<array{0: string, 1: string}> */
    private static function slugsIn(SettingSchema $setting, mixed $value): array
    {
        if ($setting->type === 'url') {
            return is_array($value) && in_array($value['type'] ?? null, self::LINK_RECORDS, true) && is_string($value['slug'] ?? null)
                ? [[$value['type'], $value['slug']]]
                : [];
        }
        $record = self::RECORDS[$setting->type] ?? null;
        if ($record === null) {
            return [];
        }
        $values = $setting->multiple() ? (is_array($value) ? $value : []) : [$value];

        return array_values(array_map(fn (string $slug) => [$record, $slug], array_filter($values, fn ($v) => is_string($v) && $v !== '')));
    }

    /**
     * Ids of the records with these slugs. Switched-off records count: the
     * merchant may switch them on, and the storefront hides them meanwhile.
     *
     * @param  list<string>  $slugs
     * @return array<string, int>
     */
    private static function find(string $record, array $slugs): array
    {
        $slugs = array_values(array_filter($slugs, fn ($s) => preg_match(self::SLUG, $s) === 1));
        if ($slugs === []) {
            return [];
        }
        $query = match ($record) {
            'category' => Category::query(),
            'product' => Product::query(),
            'brand' => Brand::query(),
            'page' => Page::query(),
            default => null,
        };

        return $query === null ? [] : $query->whereIn('slug', $slugs)->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();
    }
}
