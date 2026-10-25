<?php

namespace App\Http\Requests\Storefront;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/** A product to add to the wishlist: one that exists, is on sale and the shopper may see. */
class WishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                if (! Product::whereKey((int) $value)->where('is_active', true)->exists()) {
                    $fail('That product is not available.');
                }
            }],
        ];
    }
}
