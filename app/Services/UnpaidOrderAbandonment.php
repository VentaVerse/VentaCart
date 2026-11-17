<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Services\OrderTotals\OrderTotals;
use Illuminate\Support\Facades\DB;

/**
 * Give up on an unpaid order: one that was written at checkout but never
 * reached a status, because its payment failed, expired, was refused by
 * the gateway or was left by the shopper.
 *
 * What the order held of the customer's (store credit taken off its
 * total) is given back, and the cart lets go of it so the next Place
 * order makes a new order. The order itself stays as it is: unpaid,
 * listed only under the admin's Missing filter.
 *
 * Any payment extension calls this from its failure paths. A webhook, the
 * shopper's browser and a status lookup can reach the same order at the
 * same moment, so the check and the give-back run under the order's row
 * lock, and each total line works out what it still holds from what is
 * stored then. A second call gives back nothing more.
 *
 * An order that has a status (paid, or placed by the merchant) is never
 * touched. If one given up on is paid after all, the payment confirmation
 * (OrderStatusChanger::claimStatus()) takes again what was returned. A
 * status the merchant sets by hand does not: an abandoned order moved to
 * Canceled must not spend the credit a second time, so what the order
 * owes then is the merchant's call, made from the customer's credit panel.
 */
final class UnpaidOrderAbandonment
{
    /**
     * @return bool true when the order is unpaid (whether or not anything
     *   was left to give back), false when it has a status or is gone
     */
    public static function abandon(Order $order): bool
    {
        $unpaid = DB::transaction(function () use ($order): bool {
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

        if ($unpaid) {
            app(CheckoutService::class)->releaseCart($order);
        }

        return $unpaid;
    }

    /**
     * Never reached a status: the same test the admin's Missing filter and
     * Order::scopeVisibleToCustomer() use.
     */
    public static function isUnpaid(Order $order): bool
    {
        return $order->order_status_id === null && $order->order_status_name === null;
    }
}
