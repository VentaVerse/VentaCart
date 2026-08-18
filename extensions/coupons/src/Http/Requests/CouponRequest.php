<?php

namespace Extensions\Coupons\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;

        return [
            'code' => [
                'required', 'string', 'max:32',
                'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('coupons', 'code')->ignore($couponId),
            ],
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', Rule::in(['percent', 'fixed', 'free_shipping'])],
            'discount_value' => ['required_unless:type,free_shipping', 'nullable', 'numeric', 'min:0'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'free_shipping' => ['nullable'],
            'customer_eligibility' => ['required', Rule::in(['all', 'logged_in', 'guest'])],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_per_customer' => ['nullable', 'integer', 'min:1'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'is_active' => ['nullable'],
            'restriction_products' => ['nullable', 'string'],
            'restriction_categories' => ['nullable', 'string'],
            'restriction_brands' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Code may only contain letters, numbers, and hyphens.',
            'code.unique' => 'This coupon code is already in use.',
            'discount_value.required_unless' => 'Discount value is required for percent and fixed coupons.',
            'date_end.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
