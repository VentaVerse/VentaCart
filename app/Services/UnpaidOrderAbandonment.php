<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Services\OrderTotals\OrderTotals;
use App\Services\PaymentMethods\ClosesUnpaidPayment;
use Illuminate\Support\Facades\DB;

/**
 * Give up on an unpaid order: one that was written at checkout but never
 * reached a status, because its payment expired, was refused by the
 * gateway or was left by the shopper.
 *
 * The cart lets go of the order, so the next Place order makes a new one.
 * What the order held of the customer's (store credit taken off its
 * total) is given back only once its payment can no longer be made: the
 * caller holds proof from the gateway (a signed webhook with a final
 * status), or the order's payment method closes the payment itself
 * (ClosesUnpaidPayment), asking the gateway rather than trusting anything
 * the shopper sent. Otherwise the shopper could still pay the old order
 * and have the credit twice, so it stays held. A payment method without
 * a hosted page that stays open is closed by definition once the order
 * has no payment page.
 *
 * Any payment extension calls this from its failure paths. A webhook, the
 * shopper's browser and an admin can reach the same order at the same
 * moment, so the check and the give-back run under the order's row lock,
 * and each total line works out what it still holds from what is stored
 * then. A second call gives back nothing more. The order itself stays
 * unpaid, listed only under the admin's Missing filter.
 *
 * An order that has a status (paid, or placed by the merchant) is never
 * touched. If one given up on is paid after all, the payment confirmation
 * (OrderStatusChanger::claimStatus()) takes again what was returned, or
 * marks the order for review when it cannot.
 */
final class UnpaidOrderAbandonment
{
    /**
     * @param  bool  $paymentClosed  true only when the caller holds the
     *   gateway's own word that the payment can no longer be made (a
     *   signed webhook saying it expired); the payment method is then not
     *   asked again
     * @return bool true when the order was unpaid, its payment is closed
     *   and what it still held was given back (possibly nothing); false
     *   when it has a status, is gone, or its payment could not be
     *   confirmed closed (the credit then stays held; the cart is still
     *   let go of)
     */
    public static function abandon(Order $order, bool $paymentClosed = false): bool
    {
        $current = Order::find($order->getKey());
        if (! $current || ! self::isUnpaid($current)) {
            return false;
        }

        // Asked before the lock: closing may take a network round trip.
        $closed = $paymentClosed || self::closePayment($current);

        $released = $closed && DB::transaction(function () use ($order): bool {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();
            if (! $locked || ! self::isUnpaid($locked)) {
                return false;
            }

            $notes = OrderTotals::release($locked);
            if ($notes !== []) {
                OrderHistory::create([
                    'order_id' => $locked->id,
                    'order_status_id' => null,
                    'comment' => implode(' ', $notes),
                    'notify_customer' => false,
                ]);
            }

            return true;
        });

        app(CheckoutService::class)->releaseCart($order);

        return $released;
    }

    /**
     * Never reached a status: the same test the admin's Missing filter and
     * Order::scopeVisibleToCustomer() use.
     */
    public static function isUnpaid(Order $order): bool
    {
        return $order->order_status_id === null && $order->order_status_name === null;
    }

    /** Can this order no longer be paid? */
    private static function closePayment(Order $order): bool
    {
        $method = $order->payment_method ? PaymentManager::get((string) $order->payment_method) : null;

        if ($method instanceof ClosesUnpaidPayment) {
            try {
                return $method->closeUnpaidPayment($order);
            } catch (\Throwable $e) {
                report($e);

                return false;
            }
        }

        // No method that can close a page: closed only when the shopper
        // was never given one (the payment could not even start).
        return $order->payment_url === null;
    }
}
