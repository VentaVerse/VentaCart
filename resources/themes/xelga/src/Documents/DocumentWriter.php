<?php

namespace Themes\Xelga\Documents;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Themes\Xelga\Settings\ContrastCheck;
use Themes\Xelga\Settings\SanitizeContext;

/**
 * The only code that writes a document's draft. Every path that changes a
 * draft (the editor, restoring a revision, applying a store template) comes
 * through here, so nothing skips validation and sanitizing.
 *
 * One shared draft per document. A save names the draft_version it was
 * based on; when someone else saved first, nothing is written and the
 * caller gets a ConflictException carrying what is there now.
 */
final class DocumentWriter
{
    /**
     * Sections and blocks of an extension that is away may stay only when
     * the draft, the published version, the shipped default or one of
     * $alsoStored (a revision being restored) already holds them. A save
     * that raced a change to the row is refused by the version check, so
     * reading the row before the transaction is safe.
     *
     * @param  list<mixed>  $alsoStored
     * @return array{document: Document, adjusted: list<array>, warnings: list<array>}
     *
     * @throws ShapeException|ConflictException
     */
    public function saveDraft(string $handle, mixed $content, int $baseVersion, User $user, array $alsoStored = []): array
    {
        if (! Handles::known($handle)) {
            throw new ShapeException(['handle' => ['There is no such document.']]);
        }

        $kind = Handles::kind($handle);
        $stored = Document::where('handle', $handle)->first();
        $result = self::clean($handle, $content, [$stored?->draft, $stored?->published, Defaults::shipped($handle), ...$alsoStored]);
        $json = $result['json'];
        $warnings = $kind === 'settings' ? ContrastCheck::failures(json_decode($json, true)['schemes'] ?? []) : [];

        $document = DB::transaction(function () use ($handle, $kind, $json, $baseVersion, $user) {
            $row = Document::where('handle', $handle)->first();
            $now = now();
            $json = self::keepPlaced($json, $row);

            if ($row === null) {
                // A special layout is made by TemplateManager, with its name;
                // a draft save never brings one into being, nor back after
                // someone deleted it.
                if (Handles::isCustom($handle)) {
                    throw new MissingDocumentException();
                }
                if ($baseVersion !== 0) {
                    throw $this->conflict($handle, null);
                }
                try {
                    $id = DB::table('xelga_documents')->insertGetId([
                        'handle' => $handle,
                        'kind' => $kind,
                        'page_type' => Handles::pageType($handle),
                        'published_version' => 0,
                        'draft' => $json,
                        'draft_version' => 1,
                        'draft_base_version' => 0,
                        'draft_updated_by' => $user->id,
                        'draft_updated_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw $this->conflict($handle, Document::where('handle', $handle)->first());
                }

                return Document::findOrFail($id);
            }

            $changed = DB::table('xelga_documents')
                ->where('id', $row->id)
                ->where('draft_version', $baseVersion)
                ->update([
                    'draft' => $json,
                    'draft_version' => DB::raw('draft_version + 1'),
                    'draft_base_version' => DB::raw('COALESCE(draft_base_version, published_version)'),
                    'draft_updated_by' => $user->id,
                    'draft_updated_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($changed === 0) {
                throw $this->conflict($handle, Document::find($row->id));
            }

            return Document::findOrFail($row->id);
        });

        return ['document' => $document, 'adjusted' => $result['adjusted'], 'warnings' => $warnings];
    }

    /**
     * A document's content checked and sanitized exactly as a draft save
     * does it, encoded the way drafts are stored. Code that puts a draft
     * back by other means (undoing a starting store) runs it through here
     * too, so nothing reaches a draft unchecked.
     *
     * @param  list<mixed>  $stored  documents whose dormant sections and blocks may stay (Shape::normalize)
     * @return array{content: array, json: string, adjusted: list<array>}
     *
     * @throws ShapeException
     */
    public static function clean(string $handle, mixed $content, array $stored = []): array
    {
        if (! Handles::known($handle)) {
            throw new ShapeException(['handle' => ['There is no such document.']]);
        }
        $context = SanitizeContext::forStore(Handles::kind($handle) === 'settings' ? [] : self::schemeIds());
        $result = (new Shape($context))->normalize($handle, $content, $stored);

        return $result + ['json' => json_encode($result['content'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    }

    /**
     * A template's record of the placements it was given never shrinks: a
     * save that left one out (an older editor, a restored revision) still
     * keeps it, so what the merchant removed is never placed again.
     */
    private static function keepPlaced(string $json, ?Document $row): string
    {
        if ($row === null) {
            return $json;
        }
        // Decoded as objects, so empty maps stay {} when written back.
        $content = json_decode($json);
        if (! $content instanceof \stdClass || ! property_exists($content, 'order')) {
            return $json;
        }
        $mine = (array) ($content->placed ?? []);
        $placed = \Themes\Xelga\Extensions\Placements::placed([
            ...(array) ($row->draft['placed'] ?? []),
            ...(array) ($row->published['placed'] ?? []),
            ...$mine,
        ]);
        if ($placed === $mine) {
            return $json;
        }
        $content->placed = $placed;

        return json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * The scheme ids sections may use: those of the settings draft when
     * there is one (the editor previews it), else the published ones.
     *
     * @return list<string>
     */
    public static function schemeIds(): array
    {
        $row = Document::where('handle', Handles::SETTINGS)->first();
        $content = $row?->draft ?? $row?->published ?? Defaults::content(Handles::SETTINGS);
        $ids = array_values(array_filter(array_column((array) ($content['schemes'] ?? []), 'id'), 'is_string'));

        return $ids !== [] ? $ids : ['scheme-1'];
    }

    private function conflict(string $handle, ?Document $row): ConflictException
    {
        return new ConflictException(
            'Someone else saved this page after you opened it.',
            DocumentPresenter::state($handle, $row),
        );
    }
}
