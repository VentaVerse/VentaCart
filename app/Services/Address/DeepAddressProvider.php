<?php

namespace App\Services\Address;

use App\Contracts\AddressProvider;
use App\Models\Country;

/**
 * Generic, config-driven address provider for any country that declares deep
 * sub-levels in config('localization.deep_address'). Replaces the old
 * standalone philippine-address extension provider — the data now lives in
 * core files and the shape is driven entirely by config, so adding another
 * country is a config entry + data files, no new code.
 */
class DeepAddressProvider implements AddressProvider
{
    public function __construct(
        private string $iso,
        private array $config,
    ) {}

    public function countryId(): ?int
    {
        return Country::where('iso2', $this->iso)->value('id');
    }

    public function countryCode(): string
    {
        return $this->iso;
    }

    public function countryName(): string
    {
        return $this->config['country_name'] ?? $this->iso;
    }

    public function phoneCode(): string
    {
        return (string) ($this->config['phone_code'] ?? '');
    }

    public function fields(): array
    {
        $fields = [];

        foreach ($this->config['levels'] as $level) {
            $field = [
                'key'      => $level['key'],
                'label'    => $level['label'],
                'required' => $level['required'] ?? false,
                'endpoint' => "/address-lookup/deep/{$this->iso}/{$level['key']}",
            ];

            if (!empty($level['parent'])) {
                $field['paramFrom'] = $level['parent'];
                $field['paramName'] = 'parent';
                $field['resolveId'] = true;
            } else {
                $field['paramFrom'] = null;
                $field['paramName'] = null;
            }

            if (!empty($level['conditional'])) {
                $field['conditional'] = true;
            }

            $fields[] = $field;
        }

        return $fields;
    }
}
