<?php

namespace App\Support;

/**
 * The length limits of a shopper's address, in one place, so the address
 * book, checkout and the cart's shipping estimate accept the same values.
 *
 * Every limit is far above a real address, and each one keeps a request from
 * carrying an arbitrarily long string into the shipping providers (some of
 * which pass it on to a courier's API) or into the database.
 */
final class AddressRules
{
    /** @var array<string, int> longest value accepted, per field */
    public const MAX = [
        'first_name' => 100,
        'last_name' => 100,
        'address_1' => 500,
        'city' => 100,
        'district' => 100,
        'barangay' => 100,
        'state' => 100,
        'postcode' => 20,
        'phone' => 30,
    ];

    /** @return array<string, list<string>> the rules for a saved address */
    public static function address(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:' . self::MAX['first_name']],
            'last_name' => ['required', 'string', 'max:' . self::MAX['last_name']],
            'address_1' => ['required', 'string', 'max:' . self::MAX['address_1']],
            'city' => ['required', 'string', 'max:' . self::MAX['city']],
            'district' => ['nullable', 'string', 'max:' . self::MAX['district']],
            'barangay' => ['nullable', 'string', 'max:' . self::MAX['barangay']],
            'state' => ['nullable', 'string', 'max:' . self::MAX['state']],
            'postcode' => ['nullable', 'string', 'max:' . self::MAX['postcode']],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'phone' => ['required', 'string', 'max:' . self::MAX['phone']],
        ];
    }

    /** @return array<string, list<string>> the rules for the parts of an address a shipping estimate reads */
    public static function destination(): array
    {
        return array_intersect_key(self::address(), array_flip(['country_id', 'state', 'city', 'district', 'barangay', 'postcode']));
    }
}
