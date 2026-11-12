<?php

namespace Extensions\CourierFulfillment\Couriers\Spx;

/**
 * SPX's maximum lengths for what a person types (1.4 Create Order V2):
 * sender_name and deliver_name 64, sender_detail_address and
 * deliver_detail_address 256, deliver_instruction 256. The other limited
 * fields (state, city, district, street) come from SPX's own location list,
 * never from a form.
 *
 * The detail address is address line 1 and line 2 joined with ", ", so the
 * second line (a unit, a landmark) reaches the rider too.
 */
final class SpxFieldLimits
{
    public const NAME_MAX = 64;

    public const DETAIL_ADDRESS_MAX = 256;

    public const INSTRUCTION_MAX = 256;

    /** "Juan Dela Cruz": first and last name as SPX receives them. */
    public static function fullName(mixed $first, mixed $last): string
    {
        return trim(self::text($first) . ' ' . self::text($last));
    }

    /** "Unit 4, 123 Sample St": address lines 1 and 2, blank ones left out, joined with ", ". */
    public static function detailAddress(mixed $line1, mixed $line2): string
    {
        return implode(', ', array_filter([self::text($line1), self::text($line2)], fn (string $v) => $v !== ''));
    }

    /** A value cut to SPX's limit, counted in characters. */
    public static function cut(string $value, int $max): string
    {
        return mb_substr($value, 0, $max);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
