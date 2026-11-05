<?php

namespace Extensions\CourierFulfillment\Couriers\Lbc;

/**
 * What goes in one LBC booking: the packaging code, weight, size, value and
 * the amount to collect. Weights and volumes follow the Pickup Request data
 * dictionary: volumetric weight is L x W x H / 3500 (kg), the chargeable
 * weight is the higher of actual and volumetric, and CBM is for sea freight
 * only, so an air booking sends 0.
 */
final class LbcParcel
{
    /** The lowest declared value accepted for a booking. */
    public const MIN_DECLARED_VALUE = 500;

    public function __construct(
        public readonly int $pkg,
        public readonly float $weightKg,
        public readonly float $lengthCm,
        public readonly float $widthCm,
        public readonly float $heightCm,
        public readonly float $declaredValue,
        public readonly float $codAmount = 0.0,
        public readonly int $quantity = 1,
    ) {
        foreach (['weightKg' => $weightKg, 'lengthCm' => $lengthCm, 'widthCm' => $widthCm, 'heightCm' => $heightCm] as $name => $value) {
            if (!is_finite($value) || $value <= 0) {
                throw new LbcException("The parcel {$name} must be above zero.");
            }
        }
        if ($quantity < 1) {
            throw new LbcException('The parcel quantity must be at least 1.');
        }
        if (!is_finite($declaredValue) || $declaredValue < self::MIN_DECLARED_VALUE) {
            throw new LbcException('The declared value must be at least ' . self::MIN_DECLARED_VALUE . '.');
        }
        if (!is_finite($codAmount) || $codAmount < 0) {
            throw new LbcException('The amount to collect cannot be negative.');
        }
        if (!array_key_exists($pkg, LbcPackaging::NAMES)) {
            throw new LbcException("PKG {$pkg} is not in LBC's packaging list.");
        }
    }

    public function volumetricWeight(): float
    {
        return round($this->lengthCm * $this->widthCm * $this->heightCm / 3500, 4);
    }

    public function chargeableWeight(): float
    {
        return round(max($this->weightKg, $this->volumetricWeight()), 2);
    }
}
