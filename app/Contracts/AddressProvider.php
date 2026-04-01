<?php

namespace App\Contracts;

interface AddressProvider
{
    /** Core country ID this provider is linked to */
    public function countryId(): ?int;

    /** ISO 3166-1 alpha-2 code, e.g. 'PH' */
    public function countryCode(): string;

    /** Country display name, e.g. 'Philippines' */
    public function countryName(): string;

    /** Phone code, e.g. '63' */
    public function phoneCode(): string;

    /**
     * Return the address field hierarchy as a JSON-serializable array.
     * Each entry describes a cascading dropdown level.
     */
    public function fields(): array;
}
