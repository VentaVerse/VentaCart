<?php

namespace Themes\Xelga\Rendering;

use Themes\Xelga\Documents\Assignment;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Preview\PreviewMode;

/**
 * Which template a page draws: a custom template assigned to its subject,
 * else the page type's own template.
 *
 * A custom template is a template document whose handle is the page type,
 * a dot and a name ("product.gift-guide"). An assignment only counts when
 * it points at a custom template of the same page type that has content to
 * show: published content for shoppers, or a draft in the editor's preview.
 * Anything else (never published, wrong page type, a deleted document)
 * falls back to the page type's template, so a store never draws an empty
 * page because of a half-made layout.
 */
final class TemplateResolver
{
    /** The query parameter the editor's preview uses to show a custom template on any subject. */
    public const PREVIEW_QUERY = 'xelga_template';

    /** The subject type an assignment stores, per page type. */
    public const SUBJECTS = [
        'product' => 'product',
        'category' => 'category',
        'brand' => 'brand',
        'page' => 'page',
        'blog-post' => 'blog_post',
    ];

    public function __construct(private readonly DocumentRepository $documents)
    {
    }

    public function handleFor(string $pageType, mixed $subject = null): string
    {
        $base = in_array($pageType, Handles::TEMPLATES, true) ? $pageType : 'page';

        if (! isset(self::SUBJECTS[$base]) || ! $this->documents->ready()) {
            return $base;
        }

        $preview = PreviewMode::active();

        $asked = $preview ? $this->previewHandle($base) : null;
        if ($asked !== null) {
            return $asked;
        }

        $id = is_object($subject) && isset($subject->id) ? (int) $subject->id : 0;
        if ($id <= 0) {
            return $base;
        }

        try {
            $assignment = Assignment::query()
                ->where('subject_type', self::SUBJECTS[$base])
                ->where('subject_id', $id)
                ->with('document')
                ->first();
        } catch (\Throwable $e) {
            report($e);

            return $base;
        }

        $document = $assignment?->document;
        if (! $document instanceof Document || ! self::isCustomOf($document->handle, $base)) {
            return $base;
        }

        $usable = $document->published !== null || ($preview && $document->draft !== null);
        if (! $usable) {
            return $base;
        }

        $this->documents->remember($document);

        return $document->handle;
    }

    /** Whether a handle names a custom template of this page type ("product.gift-guide"). */
    public static function isCustomOf(string $handle, string $pageType): bool
    {
        return preg_match('/^' . preg_quote($pageType, '/') . '\.[a-z0-9][a-z0-9-]{0,47}$/', $handle) === 1;
    }

    /**
     * The custom template the editor asks to see on this page, through the
     * preview-only query parameter. Ignored outside the preview, and when it
     * names anything but an existing custom template of this page type.
     */
    private function previewHandle(string $base): ?string
    {
        $asked = request()->query(self::PREVIEW_QUERY);
        if (! is_string($asked) || ! self::isCustomOf($asked, $base)) {
            return null;
        }

        try {
            $document = Document::query()->where('handle', $asked)->first();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        if ($document === null || ($document->draft === null && $document->published === null)) {
            return null;
        }

        $this->documents->remember($document);

        return $document->handle;
    }
}
