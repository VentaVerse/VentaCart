<?php

namespace Extensions\EmailCampaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'audience_type' => ['required', Rule::in(['group', 'subscribers', 'all', 'selected'])],
            'group_id' => ['required_if:audience_type,group', 'nullable', 'integer', 'exists:customer_groups,id'],
            'customer_ids' => ['required_if:audience_type,selected', 'nullable', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'action' => ['required', Rule::in(['draft', 'send'])],
        ];
    }

    /** @return array<string, mixed>|null the audience_params column value */
    public function audienceParams(): ?array
    {
        return match ($this->input('audience_type')) {
            'group' => ['group_id' => (int) $this->input('group_id')],
            'selected' => ['customer_ids' => array_map('intval', $this->input('customer_ids', []))],
            default => null,
        };
    }
}
