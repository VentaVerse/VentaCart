<?php

namespace Extensions\CourierFulfillment\Http\Requests;

use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Support\CourierSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Showing one saved courier secret. The key must be one of this courier's
 * own encrypted fields, so the endpoint can never be pointed at any other
 * setting.
 */
class RevealCourierSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('courier-fulfillment.configure');
    }

    protected function prepareForValidation(): void
    {
        abort_if(!CourierManager::get((string) $this->route('courier')), 404);
    }

    public function rules(): array
    {
        $courier = CourierManager::get((string) $this->route('courier'));

        return [
            'key' => ['required', 'string', Rule::in($courier ? CourierSecrets::keys($courier) : [])],
        ];
    }
}
