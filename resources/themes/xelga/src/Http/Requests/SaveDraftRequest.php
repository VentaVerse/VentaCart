<?php

namespace Themes\Xelga\Http\Requests;

use Illuminate\Http\Exceptions\HttpResponseException;
use Themes\Xelga\Documents\Shape;

/** PUT documents/{handle}/draft: the whole draft and the version it was based on. */
class SaveDraftRequest extends EditorRequest
{
    protected function prepareForValidation(): void
    {
        // By now the JSON body has already been decoded, and walked by the
        // global input middleware; what bounds that work is the server's own
        // request size limit (PHP's post_max_size, enforced by Laravel's
        // ValidatePostSize, and the web server's body limit). This check
        // keeps a document over the stored size from reaching validation,
        // the sanitizer and the database.
        if (strlen((string) $this->getContent()) > Shape::MAX_BYTES + 1024) {
            throw new HttpResponseException(response()->json([
                'success' => false, 'message' => 'The page is larger than 256 KB.', 'code' => 'too_large', 'data' => null,
            ], 413));
        }
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'draft_version' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
