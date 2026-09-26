<?php

namespace Extensions\SmsUnisms\Http\Requests;

use Extensions\SmsUnisms\UniSms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The whole UniSMS page: credentials and every template, saved as one. */
class UpdateUniSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'max:500'],
            'sender_id' => ['nullable', 'string', 'max:32'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'enabled' => ['nullable'],

            'templates' => ['nullable', 'array', 'max:200'],
            'templates.*.id' => ['nullable', 'integer', Rule::exists('sms_templates', 'id')],
            'templates.*.title' => ['required', 'string', 'max:100'],
            'templates.*.message' => ['required', 'string', 'max:' . UniSms::MAX_LENGTH],
            'templates.*.status_ids' => ['required', 'array', 'min:1'],
            'templates.*.status_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('order_statuses', 'id')->where('store_id', (int) config('app.store_id', 1)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'templates.*.title.required' => 'Give this template a title.',
            'templates.*.message.required' => 'Write the message this template sends.',
            'templates.*.status_ids.required' => 'Choose at least one order status.',
            'templates.*.status_ids.min' => 'Choose at least one order status.',
            'templates.*.status_ids.*.distinct' => 'An order status can belong to one template only.',
        ];
    }
}
