<?php

namespace Extensions\CourierFulfillment\Support;

/**
 * Why a courier will not take a shipment, in words a merchant can act on: a
 * short title ("Too long for SPX") and a detail ("Length is 35 cm. SPX takes
 * up to 25 cm."). The courier's own text is never part of it; callers keep
 * that separately (in the booking's meta) for support.
 */
final class CourierRefusal
{
    public function __construct(
        public readonly string $title,
        public readonly string $detail = '',
    ) {
    }

    /** The one line shown to the merchant: "Too long for SPX. Length is 35 cm. SPX takes up to 25 cm." */
    public function message(): string
    {
        $title = rtrim(trim($this->title), '.');
        $detail = trim($this->detail);

        return $detail === '' ? "{$title}." : "{$title}. {$detail}";
    }

    /** A number as people write it: 35.000000 is 35, 12.50 is 12.5. Anything that is not a plain number is kept. */
    public static function number(mixed $value): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/^\d+(\.\d+)?$/', $text) !== 1) {
            return $text;
        }
        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }
        $text = ltrim($text, '0');

        return $text === '' || str_starts_with($text, '.') ? '0' . $text : $text;
    }
}
