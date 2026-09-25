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
     * @param  NotifyChoice|bool|null  $notify  null follows the store's
     *   ticks (NotifyChoice::follow()). The bool form is transitional: it is
     *   read as NotifyChoice::explicit($notify, []), so the history row still
     *   records exactly what the caller decided, but the doorway does NOT
     *   also announce for it. The three callers still passing a bool
     *   (OrderController, CustomerOrderCancellation, FulfillmentService)
     *   already send their own customer email/channel messages after calling
     *   apply(); if the doorway announced too, every one of those calls would
     *   tell the customer twice. Task 5 migrates each of them to pass a real
     *   NotifyChoice, deletes their own duplicate send, and removes this bool
     *   form entirely, at which point the doorway starts announcing for them.
     * @return OrderStatus|null the status row actually applied (null only if
     *   $orderStatusId doesn't resolve — the history row and order update
     *   still happen, matching addHistory()'s pre-extraction behavior, but
     *   no stock adjustment runs without a status row to read deducts_stock
     *   from).
     */
    public static function apply(Order $order, int $orderStatusId, ?string $comment = null, NotifyChoice|bool|null $notify = null, ?int $createdBy = null): ?OrderStatus
    {
        $isLegacyBool = is_bool($notify);
        $choice = match (true) {
            $notify instanceof NotifyChoice => $notify,
            $isLegacyBool => NotifyChoice::explicit($notify, []),
            default => NotifyChoice::follow(),
        };

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
        //
        // A legacy bool caller is skipped too, on purpose: it already sends
        // its own customer email/channel messages right after this call
        // returns, a habit from when this method's contract was "never sends
        // mail". Announcing here as well would tell the customer twice,
        // which is exactly what this method exists to prevent. See the
        // $notify param doc.
        if ($oldStatusId !== $orderStatusId && ! $isLegacyBool) {
            OrderNotificationService::announce($order, $newStatus, $oldStatusName, $comment, $choice, $history->id);
        }

        return $newStatus;
    }
}
