<?php

namespace Themes\Xelga\Http\Requests;

/** PATCH templates/{handle}: a new name for a special layout. */
class TemplateUpdateRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Give the layout a name.',
            'label.max' => 'Keep the name to 60 characters.',
        ];
    }
}
