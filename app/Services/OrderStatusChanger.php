<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Support\NotifyChoice;

/**
 * The ONE place an order's status is changed with its bookkeeping: history
 * row + order_status_id update + StockService adjustment, in that order —
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
     * @return OrderStatus|null the status row actually applied (null only if
     *   $orderStatusId doesn't resolve — the history row and order update
     *   still happen, matching addHistory()'s pre-extraction behavior, but
     *   no stock adjustment runs without a status row to read deducts_stock
     *   from).
     */
    public static function apply(Order $order, int $orderStatusId, ?string $comment = null, ?NotifyChoice $notify = null, ?int $createdBy = null): ?OrderStatus
    {
        $choice = $notify ?? NotifyChoice::follow();

        $oldStatusId = (int) $order->order_status_id;
        $oldStatusName = OrderStatus::find($oldStatusId)?->name;
        $newStatus = OrderStatus::find($orderStatusId);

        // Resolved before the row is written, so the row can say what was
        // decided rather than being updated a second time.
        $emails = $choice->email ?? (bool) ($newStatus?->emails_customer);

        $history = $order->histories()->create([
            'order_status_id' => $orderStatusId,
            'comment'         => $comment,
            'notify_customer' => $emails,
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

        // Nothing moved, so there is nothing to announce. This is what stops
        // the ERP setting Shipped and the courier poll mapping to Shipped ten
        // minutes later from telling the customer twice.
        if ($oldStatusId !== $orderStatusId) {
            OrderNotificationService::announce($order, $newStatus, $oldStatusName, $comment, $choice, $history->id);
        }

        return $newStatus;
    }
}
