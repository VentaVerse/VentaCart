<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rows ticked on an admin list, for one action on all of them.
 *
 * Capped at the largest page a list shows, so a hand-built request cannot
 * ask for more than a merchant could ever tick on screen.
 */
class BulkSelectionRequest extends FormRequest
{
    public const MAX = 100;

    public function authorize(): bool
    {
        // The route already sits behind the admin auth and permission middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:' . self::MAX],
            'ids.*' => ['integer', 'min:1', 'distinct'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Tick at least one row first.',
            'ids.max' => 'Pick at most ' . self::MAX . ' rows at a time.',
        ];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_values(array_map('intval', $this->validated('ids')));
    }
}
