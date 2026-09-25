<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Support\NotifyChoice;
use Illuminate\Support\Facades\DB;

/**
 * The ONE place an order's status is changed with its bookkeeping: history
 * row + order_status_id update + StockService adjustment, in that order,
 * extracted (fulfillment-tabs-v Task 4 fix round) from
 * OrderController::addHistory(), whose mechanics the fulfillment queue's
 * Cancel order action had duplicated. Both now call this for the core write;
 * everything layered on top (courier/tracking side effects, comment
 * building) stays with each caller. This method never sends anything
 * itself: it announces, and the queue sends.
 */
class OrderStatusChanger
{
    /**
     * @param  NotifyChoice|null  $notify  null follows the store's ticks
     *   (NotifyChoice::follow()). Every caller now passes a real NotifyChoice
     *   (or nothing, to follow); the transitional bool form (Task 4's shim,
     *   read as NotifyChoice::explicit($notify, []) and never announced) is
     *   gone as of Task 5, which moved every caller onto NotifyChoice and
     *   deleted the duplicate sends they used to do for themselves.
     * @param  ?string  $customerComment  what the announcement carries, when
     *   it differs from the history row's own comment (for example, a
     *   fulfilment audit line like "Order status set to X by fulfillment"
     *   that belongs on the record but not in the customer's inbox).
     *   Defaults to $comment.
     * @return OrderStatus|null the status row actually applied (null only if
     *   $orderStatusId doesn't resolve: the history row and order update
     *   still happen, matching addHistory()'s pre-extraction behavior, but
     *   no stock adjustment runs without a status row to read deducts_stock
     *   from).
     */
    public static function apply(Order $order, int $orderStatusId, ?string $comment = null, ?NotifyChoice $notify = null, ?int $createdBy = null, ?string $customerComment = null): ?OrderStatus
    {
        $choice = $notify ?? NotifyChoice::follow();

        $oldStatusId = (int) $order->order_status_id;
        $oldStatusName = OrderStatus::find($oldStatusId)?->name;
        $newStatus = OrderStatus::find($orderStatusId);

        // Resolved before the row is written, so the row can say what was
        // decided rather than being updated a second time.
        $emails = $choice->email ?? (bool) ($newStatus?->emails_customer);

        // notify_customer means "the customer was told", not "the status
        // email was sent". A caller that sends its own email instead (the
        // order confirmation, HitPay's payment confirmation) still told
        // them, so the row must still read as customer-visible even though
        // the status email itself is suppressed.
        $notifyCustomer = $emails || $choice->toldCustomerElsewhere();

        $history = $order->histories()->create([
            'order_status_id' => $orderStatusId,
            'comment'         => $comment,
            'notify_customer' => $notifyCustomer,
            'created_by'      => $createdBy,
        ]);
        $order->update(['order_status_id' => $orderStatusId]);
        // The status relation (if it was loaded) now describes the OLD status.
        // Anything reading $order->status right after a change would get a
        // stale answer, in the order page's own header as much as anywhere
        // else. Dropping the loaded relation makes the next read re-resolve.
        $order->unsetRelation('status');

        if ($newStatus) {
            StockService::adjustForStatusChange($order, $newStatus);
        }

        // The status moving is one reason to announce. A caller who took an
        // explicit position on CHANNELS is the other: an operator ticking
        // Notify (or a channel) while recording a tracking number on an
        // order that is already on its target status still means to tell
        // the customer, and that decision only ever comes from a human with
        // the order open (NotifyChoice::explicit()) or one deliberately
        // silencing everything (NotifyChoice::silent()). Every other choice,
        // including emailHandledElsewhere(), stays behind the status-moved
        // guard on an unchanged status, which is what stops the ERP setting
        // Shipped and the courier poll mapping to Shipped ten minutes later
        // from telling the customer twice about the same move, and what
        // stops a retried HitPay webhook that finds the order already on
        // the success status from re-announcing it.
        if ($oldStatusId !== $orderStatusId || $choice->isExplicit()) {
            OrderNotificationService::announce($order, $newStatus, $oldStatusName, $customerComment ?? $comment, $choice, $history->id);
        }

        return $newStatus;
    }

    /**
     * Claim an order for a target status when two independent requests can
     * both reach the same "confirm this payment" moment at nearly the same
     * instant, each having read the order before either wrote (HitPay's
     * webhook and the checkout return-URL fallback, both told the same
     * payment succeeded).
     *
     * $order may be stale: it was very likely fetched before this call,
     * possibly before the OTHER caller wrote. The guard below deliberately
     * never reads $order's own order_status_id for that reason; it re-reads
     * the row locked for the length of this transaction and decides against
     * THAT. The loser of the race, whichever caller's transaction acquires
     * the lock second, sees the winner's already-committed status and
     * applies nothing, the same outcome a genuine duplicate webhook gets.
     *
     * @return bool true if this call applied the status (the winner, or the
     *   only caller); false if the order already held it (the loser, or a
     *   plain repeat) or the order no longer exists.
     */
    public static function claimStatus(Order $order, int $orderStatusId, ?string $comment = null, ?NotifyChoice $notify = null): bool
    {
        return DB::transaction(function () use ($order, $orderStatusId, $comment, $notify) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked || (int) $locked->order_status_id === $orderStatusId) {
                return false;
            }

            self::apply($locked, $orderStatusId, $comment, $notify);

            return true;
        });
    }
}
