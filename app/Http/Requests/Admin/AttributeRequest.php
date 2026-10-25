<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An attribute as the Attributes form sends it. The group is named, and made when new. */
class AttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route already sits behind the admin auth middleware group.
        return true;
    }

    public function rules(): array
    {
        $attribute = $this->route()?->parameter('attribute');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('attributes', 'name')->ignore($attribute?->id)],
            'group' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_filterable' => ['nullable', 'boolean'],
            'show_on_card' => ['nullable', 'boolean'],
        ];
    }
}
