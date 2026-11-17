<?php

namespace Themes\Xelga\Documents;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Settings\SanitizeContext;

/**
 * Custom templates (special layouts) and which products, categories,
 * brands, pages or blog posts use them.
 *
 * A custom template is made as a copy of its page type's template, or of
 * another custom template of the same page type, and starts as a draft:
 * shoppers see it only after Publish, and until then its subjects keep the
 * page type's template. Its name and its assignments are not drafts: a
 * rename, a delete and an assignment take effect at once.
 *
 * Each subject uses at most one custom template (unique subject_type and
 * subject_id), so assigning a subject to one layout takes it from another.
 */
final class TemplateManager
{
    /** Custom templates one page type may have. */
    public const MAX_PER_PAGE_TYPE = 30;

    /** Subjects one assignment request may name. */
    public const MAX_SUBJECTS_PER_CALL = 50;

    /** Assigned ids listed per template; the count is always exact. */
    public const LISTED_SUBJECTS = 200;

    /** Characters in the name part of a custom template handle. */
    public const MAX_NAME = 48;

    /**
     * @return array{document: Document, left_out: list<string>}
     *
     * @throws ShapeException
     */
    public function create(string $pageType, string $label, ?string $from, User $user): array
    {
        if (Handles::subjectType($pageType) === null) {
            throw new ShapeException(['page_type' => ['This page cannot have special layouts.']]);
        }
        $label = $this->cleanLabel($label);
        $from = $from === null || $from === '' ? $pageType : $from;
        if ($from !== $pageType && ! (Handles::isCustom($from) && Handles::pageType($from) === $pageType)) {
            throw new ShapeException(['from' => ['A layout starts as a copy of a layout of the same page.']]);
        }

        $source = Document::where('handle', $from)->first();
        if ($from !== $pageType && $source === null) {
            throw new MissingDocumentException('The layout to copy has been deleted.');
        }
        $content = $source?->draft ?? $source?->published ?? Defaults::content($pageType);

        $document = DB::transaction(function () use ($pageType, $label, $content, $user) {
            $existing = Document::query()->where('kind', 'template')->where('page_type', $pageType)
                ->where('handle', 'like', $pageType . '.%')->lockForUpdate()->get(['id', 'handle', 'label']);

            if ($existing->count() >= self::MAX_PER_PAGE_TYPE) {
                throw new ShapeException(['label' => ['A page can have at most ' . self::MAX_PER_PAGE_TYPE . ' special layouts. Delete one to make another.']]);
            }
            $this->assertNameFree($existing, $label);

            $taken = $existing->pluck('handle')->all();
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $handle = $this->freeHandle($pageType, $label, $taken);
                [$clean, $leftOut] = $this->copyContent($handle, $pageType, $content);
                $now = now();
                try {
                    $id = DB::table('xelga_documents')->insertGetId([
                        'handle' => $handle,
                        'kind' => 'template',
                        'page_type' => $pageType,
                        'label' => $label,
                        'published_version' => 0,
                        'draft' => json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'draft_version' => 1,
                        'draft_base_version' => 0,
                        'draft_updated_by' => $user->id,
                        'draft_updated_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Someone took the same name a moment ago: try the next one.
                    $taken[] = $handle;
                    continue;
                }

                return [Document::findOrFail($id), $leftOut];
            }

            throw new ShapeException(['label' => ['That name could not be used. Try another one.']]);
        });

        [$row, $leftOut] = $document;
        $this->audit('template_create', $user, ['handle' => $row->handle, 'from' => $from, 'draft_version' => (int) $row->draft_version]);

        return ['document' => $row, 'left_out' => $leftOut];
    }

    /** @throws ShapeException */
    public function rename(string $handle, string $label, User $user): Document
    {
        $label = $this->cleanLabel($label);

        $row = DB::transaction(function () use ($handle, $label) {
            $row = $this->custom($handle, lock: true);
            $others = Document::query()->where('kind', 'template')->where('page_type', $row->page_type)
                ->where('handle', 'like', $row->page_type . '.%')->where('id', '!=', $row->id)->get(['id', 'handle', 'label']);
            $this->assertNameFree($others, $label);
            DB::table('xelga_documents')->where('id', $row->id)->update(['label' => $label, 'updated_at' => now()]);

            return $row->fresh();
        });

        $this->audit('template_rename', $user, ['handle' => $handle]);

        return $row;
    }

    /**
     * Deletes a custom template, its revisions and its assignments. Its
     * subjects use the page type's template again at once.
     *
     * @return int how many subjects used it
     *
     * @throws ShapeException|MissingDocumentException
     */
    public function delete(string $handle, User $user): int
    {
        $cleared = DB::transaction(function () use ($handle) {
            $row = $this->custom($handle, lock: true);

            return $this->deleteRow($row);
        });

        $this->audit('template_delete', $user, ['handle' => $handle, 'assignments_cleared' => $cleared]);

        return $cleared;
    }

    /**
     * Removes one custom template row and everything hanging off it, inside
     * the caller's transaction. Rows are deleted explicitly rather than left
     * to the foreign keys, so a database without enforced keys ends the same.
     */
    public function deleteRow(Document $row): int
    {
        $cleared = Assignment::where('document_id', $row->id)->delete();
        Revision::where('document_id', $row->id)->delete();
        DB::table('xelga_documents')->where('id', $row->id)->delete();

        return $cleared;
    }

    /**
     * Assigns a custom template to subjects, or clears their assignment
     * when $handle is null. A subject that used another layout moves to this
     * one; the answer says which.
     *
     * @param  list<int>  $ids
     * @return array{assigned: list<int>, cleared: list<int>, moved: list<array{subject_id: int, from: string, from_label: ?string}>}
     *
     * @throws ShapeException
     */
    public function assign(string $subjectType, array $ids, ?string $handle, User $user): array
    {
        if (! in_array($subjectType, Assignment::SUBJECTS, true)) {
            throw new ShapeException(['subject_type' => ['Layouts are assigned to products, categories, brands, pages or blog posts.']]);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || count($ids) > self::MAX_SUBJECTS_PER_CALL) {
            throw new ShapeException(['subject_ids' => ['Name between 1 and ' . self::MAX_SUBJECTS_PER_CALL . ' items.']]);
        }

        $missing = array_values(array_diff($ids, $this->existingSubjects($subjectType, $ids)));
        if ($missing !== []) {
            throw new ShapeException(['subject_ids' => ['Some of these are no longer in the store: ' . implode(', ', $missing) . '.']]);
        }

        $result = DB::transaction(function () use ($subjectType, $ids, $handle) {
            if ($handle === null) {
                $cleared = Assignment::where('subject_type', $subjectType)->whereIn('subject_id', $ids)->pluck('subject_id')->map(fn ($id) => (int) $id)->all();
                Assignment::where('subject_type', $subjectType)->whereIn('subject_id', $ids)->delete();

                return ['assigned' => [], 'cleared' => array_values($cleared), 'moved' => []];
            }

            $row = $this->custom($handle, lock: true);
            $expected = Handles::subjectType((string) $row->page_type);
            if ($expected !== $subjectType) {
                throw new ShapeException(['handle' => ["A {$this->pageWord((string) $row->page_type)} layout cannot be used for a {$this->subjectWord($subjectType)}."]]);
            }

            $moved = [];
            $current = Assignment::query()->where('subject_type', $subjectType)->whereIn('subject_id', $ids)
                ->where('document_id', '!=', $row->id)->with('document:id,handle,label')->get();
            foreach ($current as $assignment) {
                $moved[] = [
                    'subject_id' => (int) $assignment->subject_id,
                    'from' => (string) $assignment->document?->handle,
                    'from_label' => $assignment->document?->label,
                ];
            }

            $now = now();
            // One statement, keyed on the unique subject: a subject someone
            // else assigned a moment ago is moved, never duplicated.
            Assignment::query()->upsert(
                array_map(fn (int $id) => [
                    'subject_type' => $subjectType,
                    'subject_id' => $id,
                    'document_id' => $row->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $ids),
                ['subject_type', 'subject_id'],
                ['document_id', 'updated_at'],
            );

            return ['assigned' => $ids, 'cleared' => [], 'moved' => $moved];
        });

        $this->audit('assign', $user, ['subject_type' => $subjectType, 'subject_ids' => $ids, 'handle' => $handle]);

        return $result;
    }

    /**
     * Every custom template with how many subjects use it, and the version
     * of every document that has a draft (Publish and Discard name them all).
     *
     * @return array{templates: list<array>, drafts: array<string, int>}
     */
    public function summary(): array
    {
        $rows = Document::query()->where('kind', 'template')->where('handle', 'like', '%.%')
            ->orderBy('page_type')->orderBy('label')->orderBy('id')
            ->get(['id', 'handle', 'page_type', 'label', 'published_version', 'draft_version', 'published_at', 'draft_updated_at'])
            ->filter(fn (Document $d) => Handles::isCustom((string) $d->handle));
        // Drafts of a template whose extension is away are not listed: Publish and Discard leave them.
        $hasDraft = Document::query()->whereNotNull('draft')->pluck('draft_version', 'handle')
            ->filter(fn ($version, $handle) => Handles::known((string) $handle));

        $counts = Assignment::query()->whereIn('document_id', $rows->pluck('id'))
            ->selectRaw('document_id, count(*) as aggregate')->groupBy('document_id')->pluck('aggregate', 'document_id');

        $templates = [];
        foreach ($rows as $row) {
            $templates[] = [
                'handle' => (string) $row->handle,
                'page_type' => (string) $row->page_type,
                'label' => (string) ($row->label ?? $row->handle),
                'subject_type' => Handles::subjectType((string) $row->page_type),
                'count' => (int) ($counts[$row->id] ?? 0),
                'assigned' => Assignment::query()->where('document_id', $row->id)->orderBy('id')
                    ->limit(self::LISTED_SUBJECTS)->pluck('subject_id')->map(fn ($id) => (int) $id)->all(),
                'has_draft' => $hasDraft->has($row->handle),
                'is_published' => (int) $row->published_version > 0,
                'draft_version' => (int) $row->draft_version,
                'published_version' => (int) $row->published_version,
            ];
        }

        return [
            'templates' => $templates,
            'drafts' => $hasDraft->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * The ids among $ids that exist in core, read through core's models.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function existingSubjects(string $subjectType, array $ids): array
    {
        $model = self::subjectModel($subjectType);
        if ($model === null) {
            return [];
        }

        return $model::query()->whereKey($ids)->pluck((new $model)->getKeyName())->map(fn ($id) => (int) $id)->all();
    }

    /** The core model of a subject kind; null for blog posts while the blog is not installed. */
    public static function subjectModel(string $subjectType): ?string
    {
        return match ($subjectType) {
            'product' => Product::class,
            'category' => Category::class,
            'brand' => Brand::class,
            'page' => Page::class,
            'blog_post' => class_exists(\Extensions\Blog\Models\BlogPost::class) && Schema::hasTable('blog_posts')
                ? \Extensions\Blog\Models\BlogPost::class
                : null,
            default => null,
        };
    }

    /** @throws ShapeException */
    private function custom(string $handle, bool $lock = false): Document
    {
        if (! Handles::isCustom($handle)) {
            throw new ShapeException(['handle' => ['Only a special layout can be changed here.']]);
        }
        $query = Document::query()->where('handle', $handle)->where('kind', 'template');
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($row === null) {
            throw new MissingDocumentException();
        }

        return $row;
    }

    /** @throws ShapeException */
    private function cleanLabel(string $label): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', strip_tags($label)));
        if ($label === '' || mb_strlen($label) > 60) {
            throw new ShapeException(['label' => ['Give the layout a name of up to 60 characters.']]);
        }

        return $label;
    }

    /** @param \Illuminate\Support\Collection<int, Document> $rows */
    private function assertNameFree($rows, string $label): void
    {
        $wanted = mb_strtolower($label);
        foreach ($rows as $row) {
            if (mb_strtolower((string) $row->label) === $wanted) {
                throw new ShapeException(['label' => ['A layout with that name already exists.']]);
            }
        }
    }

    /** @param list<string> $taken */
    private function freeHandle(string $pageType, string $label, array $taken): string
    {
        // The name part is at most 48 characters (what the storefront's
        // resolver accepts), with room for a "-99" suffix.
        $room = self::MAX_NAME - 3;
        $name = trim(substr(Str::slug(Str::ascii($label)), 0, $room), '-');
        if ($name === '' || preg_match('/\A' . Handles::CUSTOM_NAME . '\z/', $name) !== 1) {
            $name = 'layout';
        }

        $handle = "{$pageType}.{$name}";
        for ($n = 2; in_array($handle, $taken, true) || Document::where('handle', $handle)->exists(); $n++) {
            $handle = "{$pageType}.{$name}-{$n}";
        }

        return $handle;
    }

    /**
     * The source content, cleaned for the new handle. Sections a special
     * layout cannot hold are left out, and named, rather than refused.
     *
     * @return array{0: array, 1: list<string>}
     */
    private function copyContent(string $handle, string $pageType, array $content): array
    {
        $order = array_values(array_filter((array) ($content['order'] ?? []), 'is_string'));
        $sections = (array) ($content['sections'] ?? []);
        $keep = [];
        $leftOut = [];
        foreach ($order as $id) {
            $section = $sections[$id] ?? null;
            $schema = is_array($section) ? Catalog::section((string) ($section['type'] ?? '')) : null;
            // A section kept while its extension is away goes with the copy, as it is.
            if ($schema === null && Shape::isDormant($section)) {
                $keep[] = $id;
                continue;
            }
            if ($schema === null || ! $schema->allowedIn($handle, 'template', $pageType)) {
                if (is_array($section)) {
                    $leftOut[] = (string) (($section['label'] ?? '') !== '' ? $section['label'] : ($schema?->label ?? $section['type'] ?? $id));
                }
                continue;
            }
            $keep[] = $id;
        }

        $copy = ['schema' => Shape::FORMAT, 'order' => $keep, 'sections' => array_intersect_key($sections, array_flip($keep)), 'placed' => $content['placed'] ?? []];
        $clean = (new Shape(SanitizeContext::forStore(DocumentWriter::schemeIds())))->normalize($handle, $copy, [$content])['content'];

        return [json_decode(json_encode($clean, JSON_THROW_ON_ERROR), true), $leftOut];
    }

    private function pageWord(string $pageType): string
    {
        return str_replace('-', ' ', $pageType);
    }

    private function subjectWord(string $subjectType): string
    {
        return str_replace('_', ' ', $subjectType);
    }

    /** One structured log entry per change, as Publisher writes for publish and discard. */
    private function audit(string $action, User $user, array $context): void
    {
        Log::info("Theme {$action}.", ['event' => 'theme_editor', 'theme' => 'xelga', 'action' => $action, 'user_id' => (int) $user->id] + $context);
    }
}
