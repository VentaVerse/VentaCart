<?php

namespace App\Services;

use App\Models\Coupon;

class CouponResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?Coupon $coupon = null,
        public readonly float $discount = 0,
        public readonly bool $freeShipping = false,
    ) {}

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }

    public static function ok(Coupon $coupon, float $discount, bool $freeShipping = false): self
    {
        return new self(true, 'Coupon applied.', $coupon, $discount, $freeShipping);
    }
}
