<?php

namespace App\Services\OrderTotals;

use App\Models\Order;

/**
 * One order-total modifier: a line that raises or lowers what the
 * customer pays (coupon discount, store credit, a future fee). The
 * mandatory arithmetic (subtotal, shipping, grand total) is core and is
 * NOT a provider — OpenCart let you uninstall Sub-Total and break every
 * checkout; Venta does not.
 */
interface OrderTotalProviderInterface
{
    /** Machine key, also the line's code ('coupon', 'store_credit'). */
    public function getKey(): string;

    /**
     * Inspect the context and add any lines this provider owns
     * (negative amounts reduce the total). May also adjust
     * $ctx->shippingCost (a coupon granting free shipping).
     */
    public function collect(TotalsContext $ctx): void;

    /**
     * The order exists (inside the placement transaction): persist this
     * line's side effects — burn the coupon use, write the credit
     * deduction. $line is the exact line this provider added.
     */
    public function confirm(Order $order, array $line): void;
}
