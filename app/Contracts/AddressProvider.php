<?php

namespace App\Contracts;

interface AddressProvider
{
    /** ISO 3166-1 alpha-2 code, e.g. 'PH' */
    public function countryCode(): string;

    /** Country display name, e.g. 'Philippines' */
    public function countryName(): string;

    /** Phone code, e.g. '63' */
    public function phoneCode(): string;

    /**
     * Return the address field hierarchy as a JSON-serializable array.
     * Each entry describes a cascading dropdown level.
     *
     * Keys map to database columns: 'state', 'city', 'district', 'barangay'
     *
     * Example:
     * [
     *     ['key' => 'state', 'label' => 'Province', 'required' => true, 'endpoint' => '/address-lookup/ph-provinces', 'paramFrom' => null, 'paramName' => null],
     *     ['key' => 'city',  'label' => 'City',     'required' => true, 'endpoint' => '/address-lookup/ph-cities',    'paramFrom' => 'state', 'paramName' => 'province_id', 'resolveId' => true],
     * ]
     */
    public function fields(): array;
}
