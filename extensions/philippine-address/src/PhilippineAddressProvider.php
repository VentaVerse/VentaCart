<?php

namespace Extensions\PhilippineAddress;

use App\Contracts\AddressProvider;
use App\Models\Setting;

class PhilippineAddressProvider implements AddressProvider
{
    public function countryId(): ?int
    {
        $id = Setting::where('group', 'ph_address')
            ->where('key', 'country_id')
            ->value('value');

        return $id ? (int) $id : null;
    }

    public function countryCode(): string
    {
        return 'PH';
    }

    public function countryName(): string
    {
        return 'Philippines';
    }

    public function phoneCode(): string
    {
        return '63';
    }

    public function fields(): array
    {
        return [
            [
                'key' => 'state',
                'label' => 'Province',
                'required' => true,
                'endpoint' => '/address-lookup/ph-provinces',
                'paramFrom' => null,
                'paramName' => null,
            ],
            [
                'key' => 'city',
                'label' => 'City / Municipality',
                'required' => true,
                'endpoint' => '/address-lookup/ph-cities',
                'paramFrom' => 'state',
                'paramName' => 'province_id',
                'resolveId' => true,
            ],
            [
                'key' => 'district',
                'label' => 'District',
                'required' => false,
                'endpoint' => '/address-lookup/ph-districts',
                'paramFrom' => 'city',
                'paramName' => 'city_id',
                'resolveId' => true,
                'conditional' => true,
            ],
            [
                'key' => 'barangay',
                'label' => 'Barangay',
                'required' => false,
                'endpoint' => '/address-lookup/ph-barangays',
                'paramFrom' => 'city|district',
                'paramName' => 'city_id',
                'resolveId' => true,
                'conditional' => true,
            ],
        ];
    }
}
