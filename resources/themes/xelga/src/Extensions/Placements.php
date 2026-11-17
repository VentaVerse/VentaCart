<?php

namespace Themes\Xelga\Extensions;

use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\SectionSchema;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\Shape;

/**
 * Where an extension's section or block goes by default ("the seller card
 * after the product information"), from its placements.json.
 *
 * Placement never writes. It is applied when a document is read: a
 * placement the document has not been given yet inserts its one section or
 * block, and the document records the placement's id in `placed`. Saving
 * keeps that record, so a placement is given once per document: after the
 * merchant removes what it inserted, it never comes back. It only inserts;
 * it never changes, moves or removes anything already there.
 */
final class Placements
{
    public const KEY = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** A placement's id: `{extension}:{key}`. */
    public const ID = '/^[a-z][a-z0-9-]{0,39}:[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Placement ids one document may record. */
    public const MAX_PLACED = 100;

    private const OTHERWISE = ['end', 'start', 'skip'];

    /**
     * One extension's placements, checked against the compiled sections.
     * One that breaks a rule is logged (or reported to $report) and left out.
     *
     * @param  array<string, array>  $sections  the compiled section schemas
     * @return list<array>
     */
    public static function compile(ExtensionParts $part, array $sections, ?callable $report = null): array
    {
        $out = [];
        $keys = [];
        foreach ($part->placements() as $i => $raw) {
            $key = $raw['key'] ?? null;
            $refuse = function (string $reason) use ($part, $key, $i, $report): void {
                $name = is_string($key) ? $key : "#{$i}";
                $report === null ? Registrar::refuse($part->name, "the placement {$name}: {$reason}") : $report("placement {$name}: {$reason}");
            };
            if (! is_string($key) || strlen($key) > 40 || preg_match(self::KEY, $key) !== 1) {
                $refuse('its key is 1 to 40 lower case letters, digits or hyphens');
                continue;
            }
            if (isset($keys[$key])) {
                $refuse('its key is used twice');
                continue;
            }
            $keys[$key] = true;

            $otherwise = $raw['otherwise'] ?? 'end';
            if (! in_array($otherwise, self::OTHERWISE, true)) {
                $refuse('otherwise is end, start or skip');
                continue;
            }
            $after = $raw['after'] ?? null;
            $before = $raw['before'] ?? null;
            if (($after !== null && ! is_string($after)) || ($before !== null && ! is_string($before)) || ($after !== null && $before !== null)) {
                $refuse('it names one type to go after or before, not both');
                continue;
            }

            $placement = [
                'id' => $part->name . ':' . $key,
                'extension' => $part->name,
                'after' => $after,
                'before' => $before,
                'otherwise' => $otherwise,
            ];

            if (isset($raw['block'])) {
                $block = $raw['block'];
                $target = $sections[(string) ($raw['section'] ?? '')] ?? null;
                if (! is_string($block) || Shape::extensionOf($block) !== $part->name) {
                    $refuse('it must place one of the extension\'s own blocks');
                    continue;
                }
                if ($target === null || ! (new SectionSchema($target))->blockType($block)) {
                    $refuse('the section it names does not take this block');
                    continue;
                }
                $out[] = $placement + ['kind' => 'block', 'block' => $block, 'section' => (string) $raw['section']];
                continue;
            }

            $type = $raw['section'] ?? null;
            $schema = is_string($type) && isset($sections[$type]) ? new SectionSchema($sections[$type]) : null;
            if ($schema === null || $schema->source() !== $part->name) {
                $refuse('it must place one of the extension\'s own sections');
                continue;
            }
            if (($schema->raw['main'] ?? false) === true) {
                $refuse('a main section is never placed');
                continue;
            }
            $templates = $raw['templates'] ?? null;
            if (! is_array($templates) || $templates === [] || array_filter($templates, fn ($t) => ! is_string($t)) !== []) {
                $refuse('it lists the page types it goes on');
                continue;
            }
            $preset = null;
            if (isset($raw['preset'])) {
                $preset = collect($schema->raw['presets'] ?? [])->firstWhere('key', $raw['preset']);
                if (! is_array($preset)) {
                    $refuse('its preset is not one of the section\'s');
                    continue;
                }
            }
            $out[] = $placement + [
                'kind' => 'section',
                'section' => $type,
                'templates' => array_values(array_unique($templates)),
                'custom_templates' => ($raw['custom_templates'] ?? false) === true,
                'preset' => $preset,
            ];
        }

        return $out;
    }

    /** The section or block id a placement inserts: the same in every request and every document. */
    public static function insertId(string $placementId): string
    {
        return 'p-' . substr(sha1($placementId), 0, 10);
    }

    /**
     * The document with every placement it has not been given yet. Only
     * page templates take placements.
     */
    public static function overlay(string $handle, array $content): array
    {
        $placements = Catalog::all()['placements'] ?? [];
        if ($placements === [] || Handles::kind($handle) !== 'template' || ! Handles::known($handle)) {
            return $content;
        }

        $placed = self::placed($content['placed'] ?? []);
        $changed = false;
        foreach ($placements as $placement) {
            if (in_array($placement['id'], $placed, true) || count($placed) >= self::MAX_PLACED) {
                continue;
            }
            $result = $placement['kind'] === 'block'
                ? self::placeBlock($placement, $content)
                : self::placeSection($placement, $handle, $content);
            if ($result === null) {
                continue;
            }
            $content = $result;
            $placed[] = $placement['id'];
            $changed = true;
        }

        if ($changed) {
            $content['placed'] = $placed;
        }

        return $content;
    }

    /**
     * The placement ids a document records: well formed, each once.
     *
     * @return list<string>
     */
    public static function placed(mixed $placed): array
    {
        if (! is_array($placed)) {
            return [];
        }

        $valid = array_filter($placed, fn ($id) => is_string($id) && strlen($id) <= 81 && preg_match(self::ID, $id) === 1);

        return array_slice(array_values(array_unique($valid)), 0, self::MAX_PLACED);
    }

    /** @return array|null the content with the section in, or null when it does not go in (yet) */
    private static function placeSection(array $placement, string $handle, array $content): ?array
    {
        $pageType = (string) Handles::pageType($handle);
        if (Handles::isCustom($handle) && ! $placement['custom_templates']) {
            return null;
        }
        if (! in_array($pageType, $placement['templates'], true)) {
            return null;
        }
        $schema = Catalog::section($placement['section']);
        $sections = (array) ($content['sections'] ?? []);
        $order = array_values(array_filter((array) ($content['order'] ?? []), 'is_string'));
        $id = self::insertId($placement['id']);
        $types = array_map(fn ($sid) => (string) ($sections[$sid]['type'] ?? ''), $order);

        // Already there (the merchant added one, or an earlier visit): given, nothing to insert.
        if (in_array($placement['section'], $types, true) || isset($sections[$id])) {
            return self::with($content, $order, $sections);
        }
        if ($schema === null || ! $schema->allowedIn($handle, 'template', $pageType) || count($order) >= Shape::MAX_SECTIONS) {
            return null;
        }

        $at = self::position($types, $placement);
        if ($at === null) {
            return self::with($content, $order, $sections);
        }

        $preset = (array) ($placement['preset'] ?? []);
        $blockOrder = [];
        $blocks = [];
        foreach (array_slice((array) ($preset['blocks'] ?? []), 0, $schema->blockMax()) as $n => $block) {
            $type = (string) ($block['type'] ?? '');
            if ($schema->blockType($type) === null) {
                continue;
            }
            $bid = $id . '-b' . ($n + 1);
            $blockOrder[] = $bid;
            $blocks[$bid] = ['type' => $type, 'label' => (string) ($block['label'] ?? $schema->blockType($type)['label'] ?? $type), 'disabled' => false, 'settings' => (array) ($block['settings'] ?? [])];
        }

        array_splice($order, $at, 0, [$id]);
        $sections[$id] = [
            'type' => $schema->type,
            'label' => $schema->label,
            'disabled' => false,
            'settings' => (array) ($preset['settings'] ?? []),
            'block_order' => $blockOrder,
            'blocks' => $blocks,
            'source' => $schema->source(),
            'unchecked' => true,
        ];

        return self::with($content, $order, $sections);
    }

    /** @return array|null the content with the block in, or null when it does not go in (yet) */
    private static function placeBlock(array $placement, array $content): ?array
    {
        $sections = (array) ($content['sections'] ?? []);
        $order = array_values(array_filter((array) ($content['order'] ?? []), 'is_string'));
        $target = null;
        foreach ($order as $sid) {
            if (($sections[$sid]['type'] ?? null) === $placement['section']) {
                $target = $sid;
                break;
            }
        }
        $schema = Catalog::section($placement['section']);
        $spec = $schema?->blockType($placement['block']);
        if ($target === null || $spec === null) {
            return null;
        }

        $section = (array) $sections[$target];
        $blocks = (array) ($section['blocks'] ?? []);
        $blockOrder = array_values(array_filter((array) ($section['block_order'] ?? []), 'is_string'));
        $id = self::insertId($placement['id']);
        $types = array_map(fn ($bid) => (string) ($blocks[$bid]['type'] ?? ''), $blockOrder);

        if (in_array($placement['block'], $types, true) || isset($blocks[$id])) {
            return $content;
        }
        $live = count(array_filter($blockOrder, fn ($bid) => is_array($blocks[$bid] ?? null) && ! Shape::isDormantBlock($blocks[$bid])));
        if ($live >= $schema->blockMax()) {
            return null;
        }

        $at = self::position($types, $placement);
        if ($at === null) {
            return $content;
        }

        array_splice($blockOrder, $at, 0, [$id]);
        $blocks[$id] = ['type' => $placement['block'], 'label' => (string) $spec['label'], 'disabled' => false, 'settings' => [], 'source' => $placement['extension'], 'unchecked' => true];
        $section['block_order'] = $blockOrder;
        $section['blocks'] = $blocks;
        $sections[$target] = $section;
        $content['sections'] = $sections;

        return $content;
    }

    /**
     * Where the insert goes among these types: after or before the first of
     * the named type, else at the end or the start, or nowhere (`skip`).
     *
     * @param  list<string>  $types
     */
    private static function position(array $types, array $placement): ?int
    {
        $anchor = $placement['after'] ?? $placement['before'] ?? null;
        $index = $anchor === null ? false : array_search($anchor, $types, true);
        if ($index !== false) {
            return $placement['after'] !== null ? $index + 1 : $index;
        }

        return match ($placement['otherwise']) {
            'start' => 0,
            'skip' => null,
            default => count($types),
        };
    }

    private static function with(array $content, array $order, array $sections): array
    {
        $content['order'] = $order;
        $content['sections'] = $sections;

        return $content;
    }
}
