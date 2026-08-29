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
}
