<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Themes\Xelga\Documents\ConflictException;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\DocumentPresenter;
use Themes\Xelga\Documents\DocumentWriter;
use Themes\Xelga\Documents\Duplicator;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\MissingDocumentException;
use Themes\Xelga\Documents\Publisher;
use Themes\Xelga\Documents\ReadabilityException;
use Themes\Xelga\Documents\Revision;
use Themes\Xelga\Documents\ShapeException;
use Themes\Xelga\Http\Requests\DiscardRequest;
use Themes\Xelga\Http\Requests\DuplicateRequest;
use Themes\Xelga\Http\Requests\ReadRequest;
use Themes\Xelga\Http\Requests\PublishRequest;
use Themes\Xelga\Http\Requests\RestoreRequest;
use Themes\Xelga\Http\Requests\SaveDraftRequest;

/**
 * Read, save, publish, discard and restore the editor's documents. Thin:
 * the rules live in DocumentWriter and Publisher.
 */
class DocumentController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly DocumentWriter $writer,
        private readonly Publisher $publisher,
        private readonly Duplicator $duplicator,
    ) {
    }

    public function show(ReadRequest $request, string $handle): JsonResponse
    {
        if (! Handles::exists($handle)) {
            return $this->fail('There is no such page.', 'not_found', 404);
        }

        return $this->ok(DocumentPresenter::state($handle, Document::where('handle', $handle)->first()), 'Loaded.');
    }

    public function saveDraft(SaveDraftRequest $request, string $handle): JsonResponse
    {
        if (! Handles::exists($handle)) {
            return $this->fail('There is no such page.', 'not_found', 404);
        }

        // An editor that knows no rows would send the page without them and
        // flatten it. Once the page has rows or sides, only an editor that
        // says it keeps them may save; the other is told to reload.
        if (! $request->boolean('with_rows') && self::hasLayout(Document::where('handle', $handle)->first())) {
            return $this->fail('This page was changed with a newer editor. Reload the editor to keep editing.', 'editor-outdated', 409);
        }

        return $this->attempt(function () use ($request, $handle) {
            $result = $this->writer->saveDraft($handle, $request->input('content'), (int) $request->validated('draft_version'), $request->user());

            return $this->ok([
                'document' => DocumentPresenter::state($handle, $result['document']),
                'adjusted' => $result['adjusted'],
                'warnings' => $result['warnings'],
            ], 'Draft saved.');
        });
    }

    /** Copies a section or block with new ids and saves the draft; answers the new id. */
    public function duplicate(DuplicateRequest $request, string $handle): JsonResponse
    {
        if (! Handles::exists($handle)) {
            return $this->fail('There is no such page.', 'not_found', 404);
        }

        return $this->attempt(function () use ($request, $handle) {
            $block = $request->validated('block');
            $result = $this->duplicator->duplicate(
                $handle,
                (string) $request->validated('section'),
                is_string($block) && $block !== '' ? $block : null,
                (int) $request->validated('draft_version'),
                $request->user(),
            );

            return $this->ok([
                'new_id' => $result['new_id'],
                'document' => DocumentPresenter::state($handle, $result['document']),
                'adjusted' => $result['adjusted'],
                'warnings' => $result['warnings'],
            ], 'Copied. The copy sits just below the original.');
        });
    }

    public function discard(DiscardRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request) {
            $result = $this->publisher->discard($request->user(), $request->versions());

            return $this->ok(
                ['draft_versions' => (object) $result['draft_versions'], 'deleted_templates' => $result['deleted']],
                'Draft thrown away. The editor now shows what is live.',
            );
        });
    }

    public function publish(PublishRequest $request): JsonResponse
    {
        return $this->attempt(fn () => $this->ok(
            ['documents' => (object) $this->publisher->publish($request->versions(), $request->user())],
            'Published. Shoppers see the changes now.',
        ));
    }

    public function revisions(ReadRequest $request, string $handle): JsonResponse
    {
        if (! Handles::exists($handle)) {
            return $this->fail('There is no such page.', 'not_found', 404);
        }

        $document = Document::where('handle', $handle)->first();
        $revisions = $document === null ? collect() : Revision::query()
            ->where('document_id', $document->id)
            ->orderByDesc('version')->orderByDesc('id')
            ->limit(Revision::KEEP)
            ->get(['id', 'version', 'source', 'created_by', 'created_at']);

        return $this->ok([
            'handle' => $handle,
            'published_version' => (int) ($document?->published_version ?? 0),
            'revisions' => $revisions->map(fn (Revision $r) => [
                'id' => (int) $r->id,
                'version' => (int) $r->version,
                'source' => (string) $r->source,
                'created_at' => $r->created_at?->toIso8601String(),
                'created_by' => DocumentPresenter::person($r->created_by),
                'is_live' => (int) $r->version === (int) ($document?->published_version ?? 0),
            ])->values()->all(),
        ], 'Loaded.');
    }

    public function restore(RestoreRequest $request, string $handle, int $revision): JsonResponse
    {
        $document = Handles::exists($handle) ? Document::where('handle', $handle)->first() : null;
        $row = $document ? Revision::where('document_id', $document->id)->whereKey($revision)->first() : null;
        if ($row === null) {
            return $this->fail('That earlier version is not there any more.', 'not_found', 404);
        }

        return $this->attempt(function () use ($request, $handle, $row) {
            $result = $this->publisher->restore($row, (int) $request->validated('draft_version'), $request->user());

            return $this->ok([
                'document' => DocumentPresenter::state($handle, $result['document']),
                'adjusted' => $result['adjusted'],
                'warnings' => $result['warnings'],
            ], 'Restored into the draft. Publish to put it live.');
        });
    }

    /** Whether the stored draft or published content has rows or sides. */
    private static function hasLayout(?Document $row): bool
    {
        foreach ([$row?->draft, $row?->published] as $content) {
            if (is_array($content) && (isset($content['rows']) || isset($content['sides']))) {
                return true;
            }
        }

        return false;
    }

    /** Runs a write and turns the documents' refusals into answers. */
    private function attempt(callable $write): JsonResponse
    {
        try {
            return $write();
        } catch (ShapeException $e) {
            return $this->fail($e->getMessage(), 'invalid', 422, null, $e->errors);
        } catch (ConflictException $e) {
            return $this->fail($e->getMessage(), 'conflict', 409, $e->current);
        } catch (ReadabilityException $e) {
            return $this->fail($e->getMessage(), 'readability', 422, ['failures' => $e->failures]);
        } catch (MissingDocumentException $e) {
            return $this->fail($e->getMessage(), 'not_found', 404);
        }
    }
}
