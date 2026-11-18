<?php

namespace Themes\Xelga\Documents;

use App\Support\Media\ColumnReference;
use App\Support\Media\MediaReferences;
use App\Support\Media\MediaReferenceSource;
use Themes\Xelga\Rendering\Storefront;

/**
 * The theme's documents as places that hold media library images, so the
 * library counts a photo a theme page, a draft or the theme settings show as
 * used, and moving it repoints every copy.
 *
 * Every `image` and `images` setting is a string somewhere in a document's
 * JSON, so each column is registered whole: published content (the pages
 * shoppers see and the theme settings), drafts, and the history of earlier
 * publishes. History keeps its copies current on a move but does not stop a
 * merchant deleting an image no page shows any more.
 *
 * A JSON column is read row by row on every lookup and move. A store has one
 * document per page type and template and at most thirty saved versions of
 * each, so this stays small.
 *
 * Pages drawn in this process keep the documents and photo addresses they
 * read; a move that changed any of them forgets those, so the next page
 * drawn shows the image at its new path.
 */
final class ImageReferences implements MediaReferenceSource
{
    private function __construct(private readonly MediaReferenceSource $column) {}

    public static function register(): void
    {
        $columns = [
            ColumnReference::json('xelga_documents', 'published', 'theme pages'),
            ColumnReference::json('xelga_documents', 'draft', 'theme drafts'),
            ColumnReference::json('xelga_revisions', 'content', 'theme history', countsAsUse: false),
        ];

        foreach ($columns as $column) {
            MediaReferences::register(new self($column));
        }
    }

    public function label(): string
    {
        return $this->column->label();
    }

    public function countsAsUse(): bool
    {
        return $this->column->countsAsUse();
    }

    public function owners(array $paths): array
    {
        return $this->column->owners($paths);
    }

    public function repoint(string $from, string $to): int
    {
        $changed = $this->column->repoint($from, $to);

        if ($changed > 0) {
            Storefront::forgetRequestState();
        }

        return $changed;
    }
}
