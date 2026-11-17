<?php

namespace Themes\Xelga\Documents;

use App\Models\User;

/**
 * A document as the editor receives it: draft and published content,
 * versions, and who saved last. Maps that are empty go out as {} so the
 * editor never has to tell an empty list from an empty object.
 */
final class DocumentPresenter
{
    /** @var array<int, ?array> */
    private static array $people = [];

    public static function state(string $handle, ?Document $row): array
    {
        $published = $row?->published;
        $draft = $row?->draft;

        return [
            'handle' => $handle,
            'kind' => Handles::kind($handle),
            'page_type' => Handles::pageType($handle),
            'label' => $row?->label,
            'content' => self::forClient($draft ?? $published ?? Defaults::content($handle)),
            'draft' => $draft === null ? null : self::forClient($draft),
            'published' => $published === null ? null : self::forClient($published),
            'is_default' => $published === null,
            'has_draft' => $draft !== null,
            'draft_version' => (int) ($row?->draft_version ?? 0),
            'draft_base_version' => $row?->draft_base_version,
            'published_version' => (int) ($row?->published_version ?? 0),
            'draft_updated_at' => $row?->draft_updated_at?->toIso8601String(),
            'draft_updated_by' => self::person($row?->draft_updated_by),
            'published_at' => $row?->published_at?->toIso8601String(),
            'published_by' => self::person($row?->published_by),
        ];
    }

    /** @return array{id: int, name: string}|null */
    public static function person(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        if (! array_key_exists($id, self::$people)) {
            $user = User::query()->select(['id', 'name'])->find($id);
            self::$people[$id] = $user ? ['id' => (int) $user->id, 'name' => (string) $user->name] : null;
        }

        return self::$people[$id];
    }

    /** Content with its keyed maps as objects, whatever their size. */
    public static function forClient(array $content): array
    {
        if (array_key_exists('values', $content)) {
            $content['values'] = (object) (array) $content['values'];
        }
        if (array_key_exists('sections', $content)) {
            $sections = [];
            foreach ((array) $content['sections'] as $id => $section) {
                $section = (array) $section;
                $section['settings'] = (object) (array) ($section['settings'] ?? []);
                $blocks = [];
                foreach ((array) ($section['blocks'] ?? []) as $bid => $block) {
                    $block = (array) $block;
                    $block['settings'] = (object) (array) ($block['settings'] ?? []);
                    $blocks[$bid] = $block;
                }
                $section['blocks'] = (object) $blocks;
                $section['block_order'] = array_values((array) ($section['block_order'] ?? []));
                $sections[$id] = $section;
            }
            $content['sections'] = (object) $sections;
        }

        return $content;
    }

    public static function flush(): void
    {
        self::$people = [];
    }
}
