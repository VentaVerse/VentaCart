<?php

namespace App\Services\PaymentMethods;

use App\Models\Order;

/**
 * A payment method whose payment page can stay open after the shopper
 * leaves it (a hosted gateway page). Before an unpaid order gives back
 * what it holds (store credit), its page must be closed, or the shopper
 * could still pay the old order and have the credit twice.
 *
 * Called outside any transaction: it may ask the gateway over the network.
 */
interface ClosesUnpaidPayment
{
    /**
     * Make sure this order can no longer be paid, asking the gateway
     * itself, never trusting anything the shopper sent.
     *
     * @return bool true only when the gateway confirms the payment is
     *   closed, expired or cancelled (or was never opened); false when it
     *   was paid, is still open or the gateway could not be asked
     */
    public function closeUnpaidPayment(Order $order): bool;
}
