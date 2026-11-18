<?php

namespace Themes\Xelga\Documents;

use App\Support\Media\ColumnReference;
use App\Support\Media\MediaReferences;
use App\Support\Media\MediaReferenceSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
 * A move that rewrites a document's draft or published content raises its
 * draft version. An editor left open still holds the old path under the
 * version it loaded, so its next save or publish is refused as a conflict
 * and it reloads, instead of writing the old path back over the move. The
 * history is never edited in the editor, so its rows keep their versions.
 *
 * Pages drawn in this process keep the documents and photo addresses they
 * read; a move that changed any of them forgets those, so the next page
 * drawn shows the image at its new path.
 */
final class ImageReferences implements MediaReferenceSource
{
    private const DOCUMENTS = 'xelga_documents';

    /**
     * @param  ?string  $versioned  the document column whose changed rows get a new
     *                              draft version, or null for one the editor never saves
     */
    private function __construct(
        private readonly MediaReferenceSource $column,
        private readonly ?string $versioned = null,
    ) {}

    public static function register(): void
    {
        MediaReferences::register(new self(ColumnReference::json(self::DOCUMENTS, 'published', 'theme pages'), 'published'));
        MediaReferences::register(new self(ColumnReference::json(self::DOCUMENTS, 'draft', 'theme drafts'), 'draft'));
        MediaReferences::register(new self(ColumnReference::json('xelga_revisions', 'content', 'theme history', countsAsUse: false)));
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
        $before = $this->stored();
        $changed = $this->column->repoint($from, $to);

        if ($changed > 0) {
            $this->raiseDraftVersions($before);
            Storefront::forgetRequestState();
        }

        return $changed;
    }

    /**
     * The versioned column of every document, by id, as stored before a move.
     *
     * @return array<int, ?string>
     */
    private function stored(): array
    {
        if ($this->versioned === null || ! Schema::hasTable(self::DOCUMENTS)) {
            return [];
        }

        return DB::table(self::DOCUMENTS)->pluck($this->versioned, 'id')->all();
    }

    /**
     * Raise the draft version of each document whose column the move
     * rewrote. Runs inside the move's transaction, so it rolls back with it.
     *
     * @param  array<int, ?string>  $before
     */
    private function raiseDraftVersions(array $before): void
    {
        if ($before === []) {
            return;
        }

        $ids = [];
        foreach ($this->stored() as $id => $value) {
            if (array_key_exists($id, $before) && $before[$id] !== $value) {
                $ids[] = $id;
            }
        }

        if ($ids !== []) {
            DB::table(self::DOCUMENTS)->whereIn('id', $ids)->update(['draft_version' => DB::raw('draft_version + 1')]);
        }
    }
}
