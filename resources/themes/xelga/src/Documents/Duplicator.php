<?php

namespace Themes\Xelga\Documents;

use App\Models\User;
use Themes\Xelga\Catalog\Catalog;

/**
 * Copies a section, or a block inside one, into the same document with new
 * ids, then saves the draft through DocumentWriter like any other change.
 *
 * Ids are made here rather than in the browser because the preview finds a
 * section by its id alone, across the header, the page and the footer, and
 * two editors (or an editor and an older revision) must never hand out the
 * same one. A new id is checked against every section and block id in every
 * stored document, draft and published, and in the shipped defaults.
 */
final class Duplicator
{
    private const COPY_SUFFIX = ' (copy)';

    public function __construct(private readonly DocumentWriter $writer)
    {
    }

    /**
     * @return array{document: Document, adjusted: list<array>, warnings: list<array>, new_id: string}
     *
     * @throws ShapeException|ConflictException
     */
    public function duplicate(string $handle, string $sectionId, ?string $blockId, int $baseVersion, User $user): array
    {
        if (! Handles::known($handle) || Handles::kind($handle) === 'settings') {
            throw new ShapeException(['handle' => ['Only a page, the header or the footer holds sections.']]);
        }

        $row = Document::where('handle', $handle)->first();
        // What the writer compares against: a stale base version is refused
        // there as a conflict, with the current draft attached.
        $content = $row?->draft ?? $row?->published ?? Defaults::content($handle);
        $content['sections'] = (array) ($content['sections'] ?? []);
        $content['order'] = array_values((array) ($content['order'] ?? []));

        $section = $content['sections'][$sectionId] ?? null;
        if (! is_array($section) || ! in_array($sectionId, $content['order'], true)) {
            throw new ShapeException(['section' => ['That section is not on this page any more.']]);
        }

        $schema = Catalog::section((string) ($section['type'] ?? ''));
        if ($schema === null) {
            throw new ShapeException(['section' => ['This section is no longer part of the theme, so it cannot be copied.']]);
        }

        $used = $this->usedIds($content);

        if ($blockId === null) {
            $newId = $this->copySection($content, $sectionId, $section, $schema, $used);
        } else {
            $newId = $this->copyBlock($content, $sectionId, $section, $blockId, $schema, $used);
        }

        return $this->writer->saveDraft($handle, $content, $baseVersion, $user) + ['new_id' => $newId];
    }

    /** @param array<string, true> $used */
    private function copySection(array &$content, string $sectionId, array $section, \Themes\Xelga\Catalog\SectionSchema $schema, array &$used): string
    {
        if (($schema->raw['main'] ?? false) === true) {
            throw new ShapeException(['section' => ['The page\'s main section cannot be copied.']]);
        }

        $sameType = count(array_filter($content['order'], fn ($id) => ($content['sections'][$id]['type'] ?? null) === $schema->type));
        if ($schema->limit() !== null && $sameType >= $schema->limit()) {
            throw new ShapeException(['section' => ["A page holds at most {$schema->limit()} {$schema->label}."]]);
        }
        if (count($content['order']) >= Shape::MAX_SECTIONS) {
            throw new ShapeException(['section' => ['A page holds at most ' . Shape::MAX_SECTIONS . ' sections.']]);
        }

        $copy = $section;
        $copy['label'] = self::copyLabel((string) ($section['label'] ?? '') !== '' ? (string) $section['label'] : $schema->label);
        $copy['block_order'] = [];
        $copy['blocks'] = [];
        foreach ((array) ($section['block_order'] ?? []) as $oldId) {
            $block = $section['blocks'][$oldId] ?? null;
            if (! is_string($oldId) || ! is_array($block)) {
                continue;
            }
            $id = $this->freshId((string) ($block['type'] ?? 'block'), $used);
            $copy['block_order'][] = $id;
            $copy['blocks'][$id] = $block;
        }

        $newId = $this->freshId($schema->type, $used);
        $content['sections'][$newId] = $copy;
        array_splice($content['order'], array_search($sectionId, $content['order'], true) + 1, 0, [$newId]);
        self::placeUnder($content, $sectionId, $newId);

        return $newId;
    }

    /** @param array<string, true> $used */
    private function copyBlock(array &$content, string $sectionId, array $section, string $blockId, \Themes\Xelga\Catalog\SectionSchema $schema, array &$used): string
    {
        $order = array_values((array) ($section['block_order'] ?? []));
        $block = $section['blocks'][$blockId] ?? null;
        if (! is_array($block) || ! in_array($blockId, $order, true)) {
            throw new ShapeException(['block' => ['That block is not in this section any more.']]);
        }

        $type = (string) ($block['type'] ?? '');
        $spec = $schema->blockType($type);
        if ($spec === null) {
            throw new ShapeException(['block' => ["Not a kind of block {$schema->label} holds."]]);
        }
        if (count($order) >= $schema->blockMax()) {
            throw new ShapeException(['block' => ["{$schema->label} holds at most {$schema->blockMax()}. Remove one to add another."]]);
        }
        $limit = $spec['limit'] ?? null;
        $sameType = count(array_filter($order, fn ($id) => ($section['blocks'][$id]['type'] ?? null) === $type));
        if ($limit !== null && $sameType >= (int) $limit) {
            throw new ShapeException(['block' => ["{$schema->label} holds at most {$limit} {$spec['label']}."]]);
        }

        $copy = $block;
        $copy['label'] = self::copyLabel((string) ($block['label'] ?? '') !== '' ? (string) $block['label'] : (string) $spec['label']);
        $newId = $this->freshId($type, $used);

        $section['blocks'] = (array) ($section['blocks'] ?? []);
        $section['blocks'][$newId] = $copy;
        array_splice($order, array_search($blockId, $order, true) + 1, 0, [$newId]);
        $section['block_order'] = $order;
        $content['sections'][$sectionId] = $section;

        return $newId;
    }

    /**
     * The copy goes right under its original: in the same column or side
     * while that has room. Otherwise (a one-column row, a full column or
     * side) it is left out of the rows, and Layout::normalize gives it a
     * full-width row of its own after the row of the section before it in
     * the order, which is the original.
     */
    private static function placeUnder(array &$content, string $original, string $copy): void
    {
        foreach (Layout::SIDES as $which) {
            $ids = $content['sides'][$which] ?? null;
            if (is_array($ids) && ($at = array_search($original, $ids, true)) !== false) {
                if (count($ids) < Layout::MAX_PER_SIDE) {
                    array_splice($ids, $at + 1, 0, [$copy]);
                    $content['sides'][$which] = $ids;
                }

                return;
            }
        }

        foreach (is_array($content['rows'] ?? null) ? $content['rows'] : [] as $r => $row) {
            foreach (is_array($row['columns'] ?? null) ? $row['columns'] : [] as $c => $column) {
                if (! is_array($column) || ($at = array_search($original, $column, true)) === false) {
                    continue;
                }
                if ((string) ($row['split'] ?? '12') !== '12' && count($column) < Layout::MAX_PER_COLUMN) {
                    array_splice($column, $at + 1, 0, [$copy]);
                    $content['rows'][$r]['columns'][$c] = $column;
                }

                return;
            }
        }
    }

    private static function copyLabel(string $label): string
    {
        $room = 80 - mb_strlen(self::COPY_SUFFIX);

        return rtrim(mb_substr($label, 0, $room)) . self::COPY_SUFFIX;
    }

    /**
     * The type's name and four random letters or digits, unused anywhere.
     *
     * @param  array<string, true>  $used
     */
    private function freshId(string $type, array &$used): string
    {
        $stem = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($type)), '-');
        $stem = substr($stem !== '' ? $stem : 'item', 0, 30);
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';

        do {
            $tail = '';
            for ($i = 0; $i < 4; $i++) {
                $tail .= $alphabet[random_int(0, 35)];
            }
            $id = $stem . '-' . $tail;
        } while (isset($used[$id]));

        $used[$id] = true;

        return $id;
    }

    /**
     * Every section and block id in use: this document as it is about to be
     * saved, every stored document (draft and published) and the defaults.
     *
     * @return array<string, true>
     */
    private function usedIds(array $current): array
    {
        $used = [];
        $collect = function (mixed $content) use (&$used): void {
            foreach ((array) (is_array($content) ? ($content['sections'] ?? []) : []) as $id => $section) {
                $used[(string) $id] = true;
                foreach (array_keys((array) (is_array($section) ? ($section['blocks'] ?? []) : [])) as $blockId) {
                    $used[(string) $blockId] = true;
                }
            }
        };

        $collect($current);
        Document::query()->where('kind', '!=', 'settings')->select(['id', 'draft', 'published'])
            ->each(function (Document $document) use ($collect) {
                $collect($document->draft);
                $collect($document->published);
            });
        foreach (Defaults::handles() as $handle) {
            if (Handles::known($handle) && Handles::kind($handle) !== 'settings') {
                $collect(Defaults::content($handle));
            }
        }

        return $used;
    }
}
