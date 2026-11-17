<?php

namespace Themes\Xelga\Http\Requests;

use Illuminate\Validation\Rule;
use Themes\Xelga\Documents\Handles;

/** POST templates: a new special layout for a page type, as a copy of one of that page's layouts. */
class TemplateStoreRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'page_type' => ['required', 'string', Rule::in(array_keys(Handles::CUSTOMIZABLE))],
            'label' => ['required', 'string', 'max:60'],
            'from' => ['nullable', 'string', 'max:64', 'regex:/\A[a-z0-9][a-z0-9.\-]{0,63}\z/'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Give the layout a name.',
            'label.max' => 'Keep the name to 60 characters.',
            'page_type.in' => 'This page cannot have special layouts.',
        ];
    }
}
