<?php

namespace Extensions\CourierFulfillment\Support;

use App\Models\Order;

/**
 * An order's goods weight in kilograms, from its products' own weights: the
 * variant's weight when the line has a variant that carries one, otherwise
 * the product's, converted from the product's weight unit.
 *
 * A prefill for the booking form, never a fact: packaging adds weight, and a
 * product with no weight recorded adds nothing. Null when no line has a
 * weight at all, so the form asks rather than shows a zero.
 */
final class OrderWeight
{
    /** Kilograms per unit, for the units the product form offers. */
    public const UNIT_TO_KG = [
        'kg' => 1.0,
        'g' => 0.001,
        'lb' => 0.45359237,
        'oz' => 0.028349523125,
    ];

    public static function kg(Order $order): ?float
    {
        $order->loadMissing(['products.product', 'products.productVariant']);

        $total = 0.0;
        $any = false;

        foreach ($order->products as $line) {
            $product = $line->product;
            if ($product === null) {
                continue;
            }

            $weight = (float) ($line->productVariant?->weight ?: $product->weight);
            if ($weight <= 0) {
                continue;
            }

            $factor = self::UNIT_TO_KG[strtolower((string) ($product->weight_unit ?: 'kg'))] ?? 1.0;
            $total += $weight * $factor * max(1, (int) $line->quantity);
            $any = true;
        }

        return $any ? round($total, 2) : null;
    }
}
