<?php

namespace Extensions\CourierFulfillment\Http\Requests;

use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Support\CourierSecrets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Saving one courier's settings page.
 *
 * Only the encrypted fields are checked here, and loosely: a secret is
 * trimmed and capped in length, never matched against a format, because
 * providers change key shapes without notice and a rejected valid key is
 * worse than an accepted odd one. remove_secrets[] names the secrets to
 * clear, and may only name this courier's own encrypted keys.
 *
 * The page's other fields keep the controller's allowlist as their guard.
 */
class UpdateCourierSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('courier-fulfillment.configure');
    }

    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');
        if (!is_array($settings)) {
            return;
        }

        foreach ($this->secretKeys() as $key) {
            if (isset($settings[$key]) && is_string($settings[$key])) {
                $settings[$key] = trim($settings[$key]);
            }
        }

        $this->merge(['settings' => $settings]);
    }

    public function rules(): array
    {
        $keys = $this->secretKeys();

        $rules = [
            'settings' => ['sometimes', 'array'],
            'remove_secrets' => ['sometimes', 'array'],
            'remove_secrets.*' => ['string', Rule::in($keys)],
        ];

        foreach ($keys as $key) {
            $rules['settings.' . $key] = ['nullable', 'string', 'max:' . CourierSecrets::MAX_LENGTH];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $courier = CourierManager::get((string) $this->route('courier'));
        if (!$courier) {
            return [];
        }

        $attributes = [];
        foreach (CourierSecrets::fields($courier) as $key => $field) {
            $attributes['settings.' . $key] = $field['label'];
        }

        return $attributes;
    }

    /** @return list<string> */
    private function secretKeys(): array
    {
        $courier = CourierManager::get((string) $this->route('courier'));

        return $courier ? CourierSecrets::keys($courier) : [];
    }
}
