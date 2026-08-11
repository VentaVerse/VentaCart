<?php

namespace Extensions\CourierFulfillment\Support;

use App\Models\Country;
use RuntimeException;

/**
 * The one country a pickup address may belong to, resolved at runtime.
 *
 * Every courier this module integrates today (QuadX, SPX) collects parcels in
 * the Philippines only, so the admin form pins the country rather than asking
 * a question with one answer. The pin lives HERE, in one named place, and it
 * is an ISO 3166-1 alpha-2 code looked up against `countries.iso2` — never a
 * hardcoded integer id (ids differ per install) and never the literal string
 * "Philippines" in logic (that is a display name, and a translatable one).
 *
 * The stored column stays a real foreign key to `countries`, so the model is
 * generic: the day a non-PH courier is registered, this constant is the only
 * thing that moves.
 *
 * resolve() throws rather than returning null on a missing or deactivated
 * row. A pickup address with no country is an address no courier payload can
 * be built from, so failing loudly at the form is strictly better than
 * writing a row that breaks a booking hours later.
 */
final class PickupCountry
{
    /** ISO 3166-1 alpha-2 of the only country pickup addresses are collected in today. */
    public const ISO = 'PH';

    public static function tryResolve(): ?Country
    {
        return Country::where('iso2', self::ISO)->where('is_active', true)->first();
    }

    /** @throws RuntimeException when the country row is missing or inactive. */
    public static function resolve(): Country
    {
        $country = self::tryResolve();

        if (!$country) {
            throw new RuntimeException(self::missingMessage());
        }

        return $country;
    }

    public static function missingMessage(): string
    {
        return 'Country ' . self::ISO . ' is missing or inactive, so a pickup address has no country to belong to. Activate it under Settings, Localization, Countries.';
    }
}
