<?php

namespace App\Exceptions;

/**
 * Raised when an order is placed and one or more cart lines no longer
 * cost what the shopper was shown: a special ended or started, the
 * merchant changed a price, or the shopper's customer group changed.
 *
 * It extends InvalidArgumentException so every checkout that already
 * turns a refusal into a message on the page shows this one too.
 */
class CartPricesChangedException extends \InvalidArgumentException
{
    /**
     * @param  array<int, array{item_id: int, label: string, was: float, now: float}>  $changes
     */
    public function __construct(public readonly array $changes)
    {
        $labels = implode(', ', array_unique(array_column($changes, 'label')));

        parent::__construct(
            'Some prices in your cart changed since you added them. Please review your order before placing it.'
            . ($labels !== '' ? ' Updated: ' . $labels . '.' : '')
        );
    }
}
