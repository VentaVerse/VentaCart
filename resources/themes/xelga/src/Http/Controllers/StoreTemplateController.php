<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Themes\Xelga\Documents\ConflictException;
use Themes\Xelga\Documents\MissingDocumentException;
use Themes\Xelga\Documents\ReadabilityException;
use Themes\Xelga\Documents\ShapeException;
use Themes\Xelga\Http\Requests\StoreTemplateApplyRequest;
use Themes\Xelga\Http\Requests\StoreTemplateUndoRequest;
use Themes\Xelga\StoreTemplates\StoreTemplateApplier;
use Themes\Xelga\StoreTemplates\StoreTemplates;

/**
 * "Start from a store": apply a starting store to the draft, and undo that
 * once. Thin: the rules live in StoreTemplateApplier.
 */
class StoreTemplateController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly StoreTemplateApplier $applier)
    {
    }

    public function apply(StoreTemplateApplyRequest $request, string $slug): JsonResponse
    {
        if (! StoreTemplates::exists($slug)) {
            return $this->fail('That starting store is not part of the theme.', 'not_found', 404);
        }

        return $this->attempt(function () use ($request, $slug) {
            $result = $this->applier->apply(
                $slug,
                (string) $request->validated('bring'),
                $request->versions(),
                $request->user(),
            );

            return $this->ok($result, 'Applied the ' . StoreTemplates::name($slug) . ' store to your draft.');
        });
    }

    public function undo(StoreTemplateUndoRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request) {
            $result = $this->applier->undo((string) $request->validated('token'), $request->user());

            return $this->ok($result, 'The ' . StoreTemplates::name($result['store']) . ' store is taken off your draft. Your draft is back as it was.');
        });
    }

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
