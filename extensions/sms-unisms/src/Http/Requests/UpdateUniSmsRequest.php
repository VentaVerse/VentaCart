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
            'templates.*.is_active' => ['nullable', 'boolean'],
            'templates.*.status_id' => [
                'required',
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
            'templates.*.status_id.required' => 'Choose the order status this template is for.',
            'templates.*.status_id.distinct' => 'An order status can have one template only.',
        ];
    }
}
