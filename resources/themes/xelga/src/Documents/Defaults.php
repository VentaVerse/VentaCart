<?php

namespace Themes\Xelga\Documents;

use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\StoreTemplates\Portable;
use Themes\Xelga\StoreTemplates\StoreTemplates;

/**
 * The documents the theme ships with, used until a store publishes its own:
 * a fresh install renders a finished page before anything is saved.
 *
 * The shipped files are the default starting store with its record
 * references taken out, because ids belong to a store. When a page reads
 * them, the categories, products, brands and pages that starting store
 * names by slug are looked up in this store's catalog, so a fresh install
 * shows the catalog its installer seeded. A slug the catalog lacks keeps
 * the shipped value. Nothing is written: the store's own documents start
 * when the merchant saves.
 */
final class Defaults
{
    /** @var array<string, array> */
    private static array $memo = [];

    /** @var array<string, array> */
    private static array $shipped = [];

    /** The shipped document with this store's records filled in. */
    public static function content(string $handle): array
    {
        return self::$memo[$handle] ??= self::withStoreRecords($handle, self::shipped($handle));
    }

    /** The shipped document exactly as the file has it, with no records. */
    public static function shipped(string $handle): array
    {
        if (isset(self::$shipped[$handle])) {
            return self::$shipped[$handle];
        }

        $file = Catalog::root('store-templates/_defaults/' . basename($handle) . '.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (! is_array($data)) {
            $data = Handles::kind($handle) === 'settings'
                ? ['schema' => Shape::FORMAT, 'values' => [], 'schemes' => []]
                : ['schema' => Shape::FORMAT, 'order' => [], 'sections' => []];
        }

        return self::$shipped[$handle] = $data;
    }

    /** @return list<string> */
    public static function handles(): array
    {
        return array_map(fn ($f) => basename($f, '.json'), glob(Catalog::root('store-templates/_defaults/*.json')) ?: []);
    }

    public static function flush(): void
    {
        self::$memo = [];
        self::$shipped = [];
    }

    /**
     * Each setting the default starting store fills with a record, filled
     * from this store's catalog when the record is there. Only settings
     * of sections and blocks the shipped document has are touched.
     */
    private static function withStoreRecords(string $handle, array $content): array
    {
        if (Handles::kind($handle) === 'settings') {
            return $content;
        }

        $portable = StoreTemplates::get(StoreTemplates::DEFAULT)['documents'][$handle] ?? null;
        if (! is_array($portable) || ! isset($portable['sections'])) {
            return $content;
        }

        try {
            $resolved = (new Portable(StoreTemplates::DEFAULT))->resolve($portable);
        } catch (\Throwable $e) {
            // No catalog to read (no database yet): the shipped page still draws.
            report($e);

            return $content;
        }

        $missing = [];
        foreach ($resolved['missing'] as $gap) {
            $missing[$gap['section'] . '/' . ($gap['block'] ?? '') . '/' . $gap['key']] = true;
        }

        foreach ((array) $resolved['content']['sections'] as $id => $section) {
            if (! isset($content['sections'][$id]['settings'])) {
                continue;
            }
            $content['sections'][$id]['settings'] = self::fill(
                $content['sections'][$id]['settings'],
                (array) ($portable['sections'][$id]['settings'] ?? []),
                (array) ($section['settings'] ?? []),
                $missing,
                $id . '//'
            );
            foreach ((array) ($section['blocks'] ?? []) as $blockId => $block) {
                if (! isset($content['sections'][$id]['blocks'][$blockId]['settings'])) {
                    continue;
                }
                $content['sections'][$id]['blocks'][$blockId]['settings'] = self::fill(
                    $content['sections'][$id]['blocks'][$blockId]['settings'],
                    (array) ($portable['sections'][$id]['blocks'][$blockId]['settings'] ?? []),
                    (array) ($block['settings'] ?? []),
                    $missing,
                    $id . '/' . $blockId . '/'
                );
            }
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $shipped  the shipped settings
     * @param  array<string, mixed>  $portable  the starting store's settings, by slug
     * @param  array<string, mixed>  $resolved  the same with this store's ids
     * @param  array<string, true>  $missing
     * @return array<string, mixed>
     */
    private static function fill(array $shipped, array $portable, array $resolved, array $missing, string $path): array
    {
        foreach ($resolved as $key => $value) {
            // A reference the starting store names by slug that this
            // catalog has: resolving changed the value and found a record.
            $found = array_key_exists($key, $portable) && ! isset($missing[$path . $key])
                && $value !== null && $value !== [] && $value !== $portable[$key];
            if ($found) {
                $shipped[$key] = $value;
            }
        }

        return $shipped;
    }
}
