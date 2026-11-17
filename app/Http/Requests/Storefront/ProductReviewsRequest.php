<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/** Which page of a product's approved reviews to send. */
class ProductReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . \App\Support\Reviews::PAGE_SIZE_MAX],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? \App\Support\Reviews::PAGE_SIZE);
    }

    public function page(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }
}
