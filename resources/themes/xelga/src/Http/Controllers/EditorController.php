<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Themes\Xelga\Http\BootPayload;
use Themes\Xelga\Http\Requests\SubjectPageRequest;
use Themes\Xelga\Preview\PreviewSession;

/**
 * The editor shell: a full-screen page outside the admin chrome that
 * carries the boot payload.
 *
 * Loading the shell changes nothing. The preview window is opened by the
 * editor app with a POST (CSRF token, throttled) once it has started, so a
 * link from another site that opens the editor cannot open, rotate or
 * reopen a window: it only shows a page the browser then cannot use.
 */
class EditorController extends Controller
{
    use RespondsWithJson;

    public function show(Request $request): Response
    {
        return response()
            ->view('xelga::editor.shell', ['boot' => BootPayload::build($request->user())])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Opens a fresh preview window for this admin; answers its nonce and the preview's address. */
    public function openPreview(Request $request): JsonResponse
    {
        $preview = PreviewSession::open($request->user());

        return $this->ok(BootPayload::preview($preview), 'Preview opened.');
    }

    /**
     * The storefront page of one record, for opening a page type on it from
     * the page menu. Only a record a guest can open is answered, the same
     * rule the editor's sample records follow.
     */
    public function subject(SubjectPageRequest $request): JsonResponse
    {
        $page = BootPayload::subjectPage($request->subjectType(), $request->subjectId());
        if ($page === null) {
            return $this->fail('Shoppers cannot open that page.', 'not_found', 404);
        }

        return $this->ok($page, 'Loaded.');
    }

    /** Exit: close the preview window, so no request shows drafts until the editor opens again. */
    public function closePreview(): JsonResponse
    {
        PreviewSession::close();

        return $this->ok(null, 'Preview closed.');
    }
}
