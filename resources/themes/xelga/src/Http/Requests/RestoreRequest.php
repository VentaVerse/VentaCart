<?php

namespace Themes\Xelga\Http\Requests;

/** POST documents/{handle}/revisions/{revision}/restore: the draft version it replaces. */
class RestoreRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'draft_version' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
