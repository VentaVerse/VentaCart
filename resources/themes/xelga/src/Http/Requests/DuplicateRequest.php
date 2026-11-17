<?php

namespace Themes\Xelga\Http\Requests;

use Themes\Xelga\Documents\Shape;

/** POST documents/{handle}/duplicate: copy a section, or a block in it, at the draft version given. */
class DuplicateRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'draft_version' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'section' => ['required', 'string', 'regex:' . Shape::ID],
            'block' => ['nullable', 'string', 'regex:' . Shape::ID],
        ];
    }
}
