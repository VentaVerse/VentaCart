<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Themes\Xelga\Documents\DocumentPresenter;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\MissingDocumentException;
use Themes\Xelga\Documents\ShapeException;
use Themes\Xelga\Documents\TemplateManager;
use Themes\Xelga\Http\BootPayload;
use Themes\Xelga\Http\Requests\AssignRequest;
use Themes\Xelga\Http\Requests\ReadRequest;
use Themes\Xelga\Http\Requests\TemplateDestroyRequest;
use Themes\Xelga\Http\Requests\TemplateStoreRequest;
use Themes\Xelga\Http\Requests\TemplateUpdateRequest;

/**
 * Special layouts: list, make, rename and delete them, and choose which
 * products, categories, brands, pages or blog posts use them. Thin: the
 * rules live in TemplateManager.
 */
class TemplateController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly TemplateManager $templates)
    {
    }

    /** Every special layout with its use, and the version of every draft. */
    public function index(ReadRequest $request): JsonResponse
    {
        return $this->ok($this->summary(), 'Loaded.');
    }

    public function store(TemplateStoreRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request) {
            $result = $this->templates->create(
                (string) $request->validated('page_type'),
                (string) $request->validated('label'),
                $request->validated('from'),
                $request->user(),
            );
            $handle = (string) $result['document']->handle;

            return $this->ok([
                'template' => $this->summaryOf($handle),
                'document' => DocumentPresenter::state($handle, $result['document']),
                'left_out' => $result['left_out'],
            ] + $this->summary(), 'Layout made. It shows in the store once it is published and used.', 201);
        });
    }

    public function update(TemplateUpdateRequest $request, string $handle): JsonResponse
    {
        if (! Handles::isCustom($handle)) {
            return $this->fail('There is no such layout.', 'not_found', 404);
        }

        return $this->attempt(function () use ($request, $handle) {
            $this->templates->rename($handle, (string) $request->validated('label'), $request->user());

            return $this->ok(['template' => $this->summaryOf($handle)] + $this->summary(), 'Renamed.');
        });
    }

    public function destroy(TemplateDestroyRequest $request, string $handle): JsonResponse
    {
        if (! Handles::isCustom($handle)) {
            return $this->fail('There is no such layout.', 'not_found', 404);
        }

        return $this->attempt(function () use ($request, $handle) {
            $cleared = $this->templates->delete($handle, $request->user());

            return $this->ok(['handle' => $handle, 'cleared' => $cleared] + $this->summary(), 'Layout deleted.');
        });
    }

    public function assign(AssignRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request) {
            $handle = $request->validated('handle');
            $result = $this->templates->assign(
                (string) $request->validated('subject_type'),
                $request->subjectIds(),
                is_string($handle) && $handle !== '' ? $handle : null,
                $request->user(),
            );

            return $this->ok($result + $this->summary(), $result['moved'] !== [] ? 'Moved to this layout.' : 'Saved.');
        });
    }

    /** @return array{templates: list<array>, drafts: object} */
    private function summary(): array
    {
        $summary = $this->templates->summary();
        $summary['templates'] = array_map(fn (array $t) => $t + BootPayload::previewFor($t), $summary['templates']);
        $summary['drafts'] = (object) $summary['drafts'];

        return $summary;
    }

    private function summaryOf(string $handle): ?array
    {
        foreach ($this->templates->summary()['templates'] as $template) {
            if ($template['handle'] === $handle) {
                return $template + BootPayload::previewFor($template);
            }
        }

        return null;
    }

    private function attempt(callable $write): JsonResponse
    {
        try {
            return $write();
        } catch (ShapeException $e) {
            return $this->fail($e->getMessage(), 'invalid', 422, null, $e->errors);
        } catch (MissingDocumentException $e) {
            return $this->fail($e->getMessage(), 'not_found', 404);
        }
    }
}
