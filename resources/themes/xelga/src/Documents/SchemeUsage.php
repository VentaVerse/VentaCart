<?php

namespace Themes\Xelga\Documents;

use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\SectionSchema;
use Themes\Xelga\Catalog\SettingSchema;
use Themes\Xelga\Extensions\Placements;

/**
 * Where each color scheme is used across the store, so the editor can show
 * a scheme by its colors and its places rather than by a name.
 *
 * Every page is read as the editor sees it: the draft when there is one,
 * else the published page, else the page the theme ships. A place is a
 * section or a block whose color scheme setting names the scheme (its
 * stored value, or the setting's default), or a row of columns whose
 * background is the scheme. A one-column row has no background of its own.
 */
final class SchemeUsage
{
    /**
     * Each scheme id of the store's settings, with the places that use it,
     * header first, then the pages, then the footer.
     *
     * @return array<string, list<array{handle: string, section: ?string, block: ?string, row: ?string, label: string}>>
     */
    public static function forStore(): array
    {
        $rows = Document::query()->get()->keyBy('handle');

        $usage = [];
        foreach (self::read($rows, Handles::SETTINGS)['schemes'] ?? [] as $scheme) {
            if (is_array($scheme) && is_string($scheme['id'] ?? null)) {
                $usage[$scheme['id']] = [];
            }
        }

        foreach (self::handles($rows->keys()->all()) as $handle) {
            $content = self::read($rows, $handle);
            if ($content === null) {
                continue;
            }
            foreach (self::places($handle, $content) as [$id, $place]) {
                $usage[$id][] = $place;
            }
        }

        return $usage;
    }

    /**
     * The page documents a store has, in the order a merchant reads them.
     *
     * @param  list<string>  $stored  handles with a row in the database
     * @return list<string>
     */
    private static function handles(array $stored): array
    {
        $custom = array_values(array_filter($stored, fn ($handle) => Handles::isCustom((string) $handle)));
        sort($custom);

        return array_values(array_unique([
            'header',
            ...Handles::TEMPLATES,
            ...array_keys(Handles::extensionTemplates()),
            ...$custom,
            'footer',
        ]));
    }

    /** The content the editor shows for a handle, or null when there is none. */
    private static function read($rows, string $handle): ?array
    {
        $row = $rows->get($handle);
        $content = $row?->draft ?? $row?->published;
        if ($content === null) {
            if (Handles::isCustom($handle)) {
                return null;
            }
            $content = Defaults::content($handle);
        }
        if (! is_array($content)) {
            return null;
        }

        return $handle === Handles::SETTINGS ? $content : Placements::overlay($handle, $content);
    }

    /**
     * Every use of a scheme in one document, as [scheme id, place] pairs.
     *
     * @return list<array{0: string, 1: array{handle: string, section: ?string, block: ?string, row: ?string, label: string}}>
     */
    private static function places(string $handle, array $content): array
    {
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
        $order = is_array($content['order'] ?? null) ? $content['order'] : array_keys($sections);
        $out = [];

        foreach ($order as $sid) {
            $section = is_string($sid) ? ($sections[$sid] ?? null) : null;
            $schema = is_array($section) ? Catalog::section((string) ($section['type'] ?? '')) : null;
            if ($schema === null) {
                continue;
            }
            $label = self::sectionLabel($section, $schema);
            $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
            foreach (self::schemesOf($schema->settings(), $settings) as $id) {
                $out[] = [$id, self::place($handle, $sid, null, null, $label)];
            }

            $blocks = is_array($section['blocks'] ?? null) ? $section['blocks'] : [];
            $blockOrder = is_array($section['block_order'] ?? null) ? $section['block_order'] : array_keys($blocks);
            foreach ($blockOrder as $bid) {
                $block = is_string($bid) ? ($blocks[$bid] ?? null) : null;
                if (! is_array($block) || $schema->blockType((string) ($block['type'] ?? '')) === null) {
                    continue;
                }
                $type = (string) $block['type'];
                $own = $schema->carryDown($type, $settings, is_array($block['settings'] ?? null) ? $block['settings'] : []);
                $blockLabel = trim((string) ($block['label'] ?? '')) ?: (string) ($schema->blockType($type)['label'] ?? $type);
                foreach (self::schemesOf($schema->blockSettings($type), $own) as $id) {
                    $out[] = [$id, self::place($handle, $sid, $bid, null, "{$blockLabel} in {$label}")];
                }
            }
        }

        if (Handles::kind($handle) === 'template') {
            foreach (Layout::rows($content) as $row) {
                $scheme = $row['settings']['scheme'] ?? '';
                if (count($row['columns']) < 2 || ! is_string($scheme) || $scheme === '') {
                    continue;
                }
                $names = [];
                foreach (array_merge(...$row['columns']) as $sid) {
                    $section = $sections[$sid] ?? null;
                    $schema = is_array($section) ? Catalog::section((string) ($section['type'] ?? '')) : null;
                    if ($schema !== null) {
                        $names[] = self::sectionLabel($section, $schema);
                    }
                }
                $label = $names === [] ? 'Empty row' : 'Row with ' . self::listWords($names);
                $out[] = [$scheme, self::place($handle, null, null, $row['id'], $label)];
            }
        }

        return $out;
    }

    /**
     * The scheme ids the color scheme settings of a section or block name.
     *
     * @param  array<string, SettingSchema>  $schemas
     * @return list<string>
     */
    private static function schemesOf(array $schemas, array $values): array
    {
        $ids = [];
        foreach ($schemas as $key => $setting) {
            if ($setting->type !== 'color-scheme') {
                continue;
            }
            $value = $values[$key] ?? $setting->default;
            if (is_string($value) && $value !== '') {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return array{handle: string, section: ?string, block: ?string, row: ?string, label: string} */
    private static function place(string $handle, ?string $section, ?string $block, ?string $row, string $label): array
    {
        return ['handle' => $handle, 'section' => $section, 'block' => $block, 'row' => $row, 'label' => $label];
    }

    private static function sectionLabel(array $section, SectionSchema $schema): string
    {
        $label = trim((string) ($section['label'] ?? ''));

        return $label !== '' ? $label : $schema->label;
    }

    /** @param list<string> $names */
    private static function listWords(array $names): string
    {
        if (count($names) < 2) {
            return (string) ($names[0] ?? '');
        }
        $last = array_pop($names);

        return implode(', ', $names) . ' and ' . $last;
    }
}
