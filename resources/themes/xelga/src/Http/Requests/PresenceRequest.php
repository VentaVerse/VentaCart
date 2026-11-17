<?php

namespace Themes\Xelga\Http\Requests;

/** POST presence: who is in the editor, and on which page. */
class PresenceRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'handle' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9.\-]{0,63}$/'],
        ];
    }
}
