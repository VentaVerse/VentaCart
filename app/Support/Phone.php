<?php

namespace App\Support;

/**
 * A phone number the way a person reads it beside an address: the country's
 * dialling code in front of the number that was typed. Numbers are stored
 * as typed (checkout and the address book keep the national number and the
 * country separately), so the code is added at display time from the
 * address's country. A number already carrying a "+" is left alone.
 */
final class Phone
{
    public static function display(?string $phone, ?string $phoneCode): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $code = ltrim(trim((string) $phoneCode), '+');
        if ($code === '' || str_starts_with($phone, '+')) {
            return $phone;
        }

        return '+' . $code . ' ' . $phone;
    }

    /**
     * The same number as one dialable string: +, country code, digits.
     *
     * Numbers are stored as typed, so what arrives is a national number
     * ("09171234567", "9171234567") that only means something alongside its
     * country. This is where the two are put together, because anything that
     * leaves the building (a gateway, an API) needs the whole number and
     * cannot ask which country it came from.
     *
     * Null when there is nothing dialable, rather than a half number: a
     * caller can then say so instead of sending into the dark.
     */
    public static function e164(?string $phone, ?string $phoneCode): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        // Already international. Keep it, minus the spacing people type.
        if (str_starts_with($phone, '+')) {
            $digits = preg_replace('/\D/', '', $phone);

            return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+' . $digits : null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        $code = ltrim(preg_replace('/\D/', '', (string) $phoneCode), '0');

        if ($digits === '' || $code === '') {
            return null;
        }

        // A trunk prefix belongs to dialling inside the country and has no
        // place in front of a country code: 09171234567 is +639171234567.
        $national = ltrim($digits, '0');

        // Already carries its own country code, typed without the plus.
        if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 6) {
            $national = substr($digits, strlen($code));
        }

        if ($national === '') {
            return null;
        }

        $full = $code . $national;

        return strlen($full) >= 8 && strlen($full) <= 15 ? '+' . $full : null;
    }
}
