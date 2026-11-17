<?php

namespace App\Http\Requests\Admin\Content;

use App\Models\NavMenu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A menu's name, as typed when the menu is added or renamed. The key a
 * theme finds the menu by is never taken from the request.
 *
 * Adding and renaming report into their own error bags, because both
 * forms sit on the same page and each should show only its own errors.
 */
class NavMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route's permission (menus.create or menus.edit) is checked by
        // the admin middleware before the request reaches here.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->route()?->parameter('menu') instanceof NavMenu ? 'renameMenu' : 'addMenu';

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim(preg_replace('/\s+/u', ' ', $this->input('name')))]);
        }
    }

    public function rules(): array
    {
        $menu = $this->route()?->parameter('menu');

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('nav_menus', 'name')->ignore($menu instanceof NavMenu ? $menu->id : null),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give the menu a name.',
            'name.unique' => 'Another menu already has that name.',
        ];
    }
}
