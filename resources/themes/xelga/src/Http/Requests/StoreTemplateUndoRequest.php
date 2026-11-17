<?php

namespace Themes\Xelga\Http\Requests;

/** POST store-templates/undo: the token the apply answered. */
class StoreTemplateUndoRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{40}\z/'],
        ];
    }
}
