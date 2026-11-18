<?php

namespace Themes\Xelga\Documents;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Themes\Xelga\Settings\ContrastCheck;
use Themes\Xelga\Settings\SanitizeContext;

/**
 * Publish, discard and restore. Publishing copies every draft to published
 * in one transaction, writes a revision per document, clears the drafts and
 * prunes revisions to the newest 30; it is all or nothing.
 */
final class Publisher
{
    public function __construct(private readonly DocumentWriter $writer)
    {
    }

    /**
     * @param  array<string, int>  $versions  handle => the draft_version the editor last saw
     * @return array<string, array{published_version: int, draft_version: int}>
     *
     * @throws ConflictException|ShapeException|ReadabilityException
     */
    public function publish(array $versions, User $user): array
    {
        foreach (array_keys($versions) as $handle) {
            if (! Handles::known((string) $handle)) {
                throw new ShapeException(["versions.{$handle}" => ['There is no such document.']]);
            }
        }

        $result = DB::transaction(function () use ($versions, $user) {
            // A template whose extension is away is left as it is: not
            // published, not a conflict. It waits for its extension.
            $rows = Document::query()
                ->where(fn ($q) => $q->whereNotNull('draft')->orWhereIn('handle', array_keys($versions)))
                ->lockForUpdate()
                ->get()
                ->filter(fn (Document $row) => Handles::known((string) $row->handle))
                ->keyBy('handle');

            $this->checkVersions($rows, $versions);

            $drafts = $rows->filter(fn (Document $row) => $row->draft !== null);
            if ($drafts->isEmpty()) {
                throw new ShapeException(['versions' => ['There are no changes to publish.']]);
            }

            // The catalog may have changed since the drafts were saved, so
            // they are checked again, strictly, before anything goes live.
            $settings = $drafts->get(Handles::SETTINGS)?->draft
                ?? $rows->get(Handles::SETTINGS)?->published
                ?? Document::where('handle', Handles::SETTINGS)->value('published')
                ?? null;
            $settings = is_string($settings) ? json_decode($settings, true) : $settings;
            $settings ??= Defaults::content(Handles::SETTINGS);

            $failures = ContrastCheck::failures((array) ($settings['schemes'] ?? []));
            if ($failures !== []) {
                throw new ReadabilityException($failures);
            }

            $schemeIds = array_values(array_filter(array_column((array) ($settings['schemes'] ?? []), 'id'), 'is_string'));
            $context = SanitizeContext::forStore($schemeIds);
            $now = now();
            $out = [];

            foreach ($drafts as $handle => $row) {
                // A draft saved before its Header, Footer or page content was
                // locked gets it back as the storefront draws it, rather than
                // being refused.
                $draft = \Themes\Xelga\Rendering\Storefront::withLockedSections($row->draft, (string) $handle);
                $clean = (new Shape($context))->normalize($handle, $draft, [$row->draft])['content'];
                $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $version = $row->published_version + 1;

                Revision::create([
                    'document_id' => $row->id,
                    'version' => $version,
                    'content' => json_decode($json, true),
                    'source' => 'publish',
                    'created_by' => $user->id,
                ]);

                DB::table('xelga_documents')->where('id', $row->id)->update([
                    'published' => $json,
                    'published_version' => $version,
                    'published_at' => $now,
                    'published_by' => $user->id,
                    'draft' => null,
                    'draft_version' => $row->draft_version + 1,
                    'draft_base_version' => null,
                    'updated_at' => $now,
                ]);

                $this->prune($row->id);

                $out[$handle] = ['published_version' => $version, 'draft_version' => $row->draft_version + 1];
            }

            return $out;
        });

        $this->audit('publish', $user, $result);

        return $result;
    }

    /**
     * Throw every draft away, so the editor shows what is live. A special
     * layout that was never published has nothing live to go back to, so it
     * is deleted with its assignments: its subjects already showed the page
     * type's template.
     *
     * @param  array<string, int>|null  $versions  when given, refused if a draft moved on since
     * @return array{draft_versions: array<string, int>, deleted: list<string>} new draft_version per handle, and the layouts deleted
     */
    public function discard(User $user, ?array $versions = null): array
    {
        $deleted = [];
        $out = DB::transaction(function () use ($user, $versions, &$deleted) {
            $rows = Document::query()->whereNotNull('draft')->lockForUpdate()->get()
                ->filter(fn (Document $row) => Handles::known((string) $row->handle))
                ->keyBy('handle');
            if ($versions !== null) {
                $this->checkVersions($rows, $versions);
            }
            $out = [];
            foreach ($rows as $handle => $row) {
                if (Handles::isCustom((string) $handle) && $row->published === null) {
                    app(TemplateManager::class)->deleteRow($row);
                    $deleted[] = (string) $handle;
                    continue;
                }
                DB::table('xelga_documents')->where('id', $row->id)->update([
                    'draft' => null,
                    'draft_version' => $row->draft_version + 1,
                    'draft_base_version' => null,
                    'draft_updated_by' => $user->id,
                    'draft_updated_at' => now(),
                    'updated_at' => now(),
                ]);
                $out[$handle] = $row->draft_version + 1;
            }

            return $out;
        });

        $this->audit('discard', $user, array_map(fn (int $draftVersion) => ['draft_version' => $draftVersion], $out) + array_fill_keys($deleted, ['deleted' => 1]));

        return ['draft_versions' => $out, 'deleted' => $deleted];
    }

    /**
     * Copy a revision into the draft. Nothing goes live until Publish.
     *
     * @return array{document: Document, adjusted: list<array>, warnings: list<array>}
     */
    public function restore(Revision $revision, int $baseVersion, User $user): array
    {
        $handle = (string) $revision->document->handle;
        // A revision from before its Header, Footer or page content was
        // locked restores with it, as the storefront would draw it.
        $content = \Themes\Xelga\Rendering\Storefront::withLockedSections($revision->content, $handle);
        $result = $this->writer->saveDraft($handle, $content, $baseVersion, $user, [$revision->content]);

        $this->audit('restore', $user, [$handle => [
            'restored_version' => (int) $revision->version,
            'draft_version' => (int) $result['document']->draft_version,
        ]]);

        return $result;
    }

    /**
     * One structured log entry per change that affects the live store or
     * throws work away. Core's activity log records customer activity only,
     * so the application log is the record of who changed the theme.
     *
     * @param  array<string, array<string, int>>  $documents  handle => versions after the change
     */
    private function audit(string $action, User $user, array $documents): void
    {
        Log::info("Theme {$action}.", [
            'event' => 'theme_editor',
            'theme' => 'xelga',
            'action' => $action,
            'user_id' => (int) $user->id,
            'handles' => array_keys($documents),
            'versions' => $documents,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Document>  $rows
     * @param  array<string, int>  $versions
     */
    private function checkVersions($rows, array $versions): void
    {
        foreach ($rows as $handle => $row) {
            if ($row->draft !== null && ! array_key_exists($handle, $versions)) {
                throw new ConflictException('Someone else has changes on a page you have not loaded.', DocumentPresenter::state($handle, $row));
            }
        }
        foreach ($versions as $handle => $version) {
            $row = $rows->get($handle);
            if ((int) ($row?->draft_version ?? 0) !== (int) $version) {
                throw new ConflictException('Someone else saved after you opened the editor.', DocumentPresenter::state((string) $handle, $row));
            }
        }
    }

    private function prune(int $documentId): void
    {
        $keep = Revision::where('document_id', $documentId)
            ->orderByDesc('version')->orderByDesc('id')
            ->limit(Revision::KEEP)
            ->pluck('id');

        Revision::where('document_id', $documentId)->whereNotIn('id', $keep)->delete();
    }
}
