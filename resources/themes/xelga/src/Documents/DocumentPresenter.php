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
        $published = self::withLocked($row?->published, $handle);
        $draft = self::withLocked($row?->draft, $handle);
        // What the editor edits: with any extension placement it has not
        // been given, so saving keeps both the section and the record.
        $content = self::withMoves(self::withLocked(\Themes\Xelga\Extensions\Placements::overlay($handle, $draft ?? $published ?? Defaults::content($handle)), $handle), $handle);

        return [
            'handle' => $handle,
            'kind' => Handles::kind($handle),
            'page_type' => Handles::pageType($handle),
            'label' => $row?->label,
            'content' => self::objects($content['content']),
            // What the layout normalizer moved in `content` (a section that
            // no longer fits where it was put, sides on a page without them),
            // so the editor can say what changed since the page was saved.
            'adjusted' => $content['moved'],
            'draft' => $draft === null ? null : self::forClient($draft, $handle),
            'published' => $published === null ? null : self::forClient($published, $handle),
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

    /**
     * Content with its keyed maps as objects, whatever their size. Given
     * the handle, a page's rows and sides are read as the storefront reads
     * them, so a section an extension placed, or one a theme update made
     * too wide for its column, shows where the page draws it.
     */
    public static function forClient(array $content, ?string $handle = null): array
    {
        return self::objects(self::withMoves($content, $handle)['content']);
    }

    /**
     * The content with its rows and sides read as the storefront reads
     * them, and what that moved. Without a handle, or for the theme
     * settings, it is returned as it is.
     *
     * @return array{content: array, moved: list<array{path: string, reason: string, value: string}>}
     */
    public static function withMoves(array $content, ?string $handle): array
    {
        if ($handle === null || Handles::kind($handle) === 'settings') {
            return ['content' => $content, 'moved' => []];
        }

        try {
            return Layout::normalize($content, app(LayoutRules::class, ['handle' => $handle]));
        } catch (\Throwable $e) {
            // The editor still gets the page as stored, rows included,
            // so a save does not lose them.
            report($e);

            return ['content' => $content, 'moved' => []];
        }
    }

    /** A stored document with the sections it always holds, as the storefront draws it. */
    private static function withLocked(?array $content, string $handle): ?array
    {
        return $content === null ? null : \Themes\Xelga\Rendering\Storefront::withLockedSections($content, $handle);
    }

    /** Content with its keyed maps as objects, whatever their size. */
    private static function objects(array $content): array
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
