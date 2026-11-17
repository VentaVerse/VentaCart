<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/** Which of the signed-in customer's saved addresses to quote delivery for. */
class ShippingRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
