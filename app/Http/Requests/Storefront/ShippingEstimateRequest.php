<?php

namespace App\Http\Requests\Storefront;

use App\Support\AddressRules;
use Illuminate\Foundation\Http\FormRequest;

/** Where the cart's shipping estimate is for: the same limits as a saved address. */
class ShippingEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return AddressRules::destination();
    }
}
