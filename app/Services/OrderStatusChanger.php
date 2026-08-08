<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatus;

/**
 * The ONE place an order's status is changed with its bookkeeping: history
 * row + order_status_id update + StockService adjustment, in that order —
 * extracted (fulfillment-tabs-v3 Task 4 fix round) from
 * OrderController::addHistory(), whose mechanics the fulfillment queue's
 * Cancel order action had duplicated. Both now call this for the core write;
 * everything layered on top (customer notification emails, courier/tracking
 * side effects, comment building) stays with each caller — this method never
 * sends mail.
 */
class OrderStatusChanger
{
    /**
     * @return OrderStatus|null the status row actually applied (null only if
     *   $orderStatusId doesn't resolve — the history row and order update
     *   still happen, matching addHistory()'s pre-extraction behavior, but
     *   no stock adjustment runs without a status row to read deducts_stock
     *   from).
     */
    public static function apply(Order $order, int $orderStatusId, ?string $comment = null, bool $notifyCustomer = false, ?int $createdBy = null): ?OrderStatus
    {
        $order->histories()->create([
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

        $newStatus = OrderStatus::find($orderStatusId);
        if ($newStatus) {
            StockService::adjustForStatusChange($order, $newStatus);
        }

        return $newStatus;
    }
}
