<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Support\NotifyChoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A customer cancels their own order from their account, while it still
 * sits on a status the merchant marked "customer may cancel".
 *
 * The rule is data, not code: each status says whether its orders may be
 * cancelled by the customer (customer_can_cancel) and where a cancellation
 * sends them (customer_cancel_status_id). No destination chosen, no
 * cancellation offered. Because the move goes
 * through OrderStatusChanger, stock returns, history is written, the
 * fulfillment tab and the ERP follow, exactly as an admin cancellation
 * would.
 *
 * The check and the write happen under a row lock: a payment webhook
 * arriving in the same second cannot be overwritten. Whoever writes first
 * wins; the other sees the new status and stops.
 */
class CustomerOrderCancellation
{
    /** Can this customer cancel this order right now? Read-only; the page uses it to show the button. */
    public static function allowed(Order $order, ?Customer $customer): bool
    {
        if (! $customer || (int) $order->customer_id !== (int) $customer->id) {
            return false;
        }

        return self::target($order) !== null;
    }

    /**
     * The same answer for a page of orders, with each status's destination
     * looked up once instead of once per row.
     *
     * @param  iterable<Order>  $orders
     * @return array<int, bool>  keyed by order id
     */
    public static function allowedMany(iterable $orders, ?Customer $customer): array
    {
        $out = [];
        $targets = [];

        foreach ($orders as $order) {
            $statusId = (int) $order->order_status_id;
            $targets[$statusId] ??= self::target($order) !== null;
            $out[$order->id] = $customer
                && (int) $order->customer_id === (int) $customer->id
                && $targets[$statusId];
        }

        return $out;
    }

    /**
     * The status a customer cancellation moves this order to: the one its
     * current status names, when that status allows cancelling. Null when it
     * does not, or names nothing, or names a status of another store, and
     * then nothing is offered. Never a guess by status name: a name can be
     * renamed or deleted, and a guess that silently stops working is worse
     * than a button that never appears until the store picks one.
     */
    public static function target(Order $order): ?OrderStatus
    {
        $status = $order->status;
        if (! $status?->customer_can_cancel || ! $status->customer_cancel_status_id) {
            return null;
        }

        return OrderStatus::where('store_id', $order->store_id)
            ->whereKeyNot($status->id)
            ->find($status->customer_cancel_status_id);
    }

    /**
     * @throws RuntimeException with a customer-facing message when the order can no longer be cancelled
     */
    public static function cancel(Order $order, Customer $customer, ?string $reason = null): OrderStatus
    {
        $reason = trim((string) $reason);
        $comment = 'Cancelled by customer' . ($reason !== '' ? ': ' . $reason : '') . '.';

        [$oldName, $target] = DB::transaction(function () use ($order, $customer, $comment) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! self::allowed($locked, $customer)) {
                throw new RuntimeException('This order can no longer be cancelled. It may already be paid or on its way.');
            }

            $target = self::target($locked);
            $oldName = $locked->order_status_name;

            // The customer is always told their own cancellation worked;
            // any registered channel (SMS and the like) follows the store's
            // own ticks, same as every other status change.
            OrderStatusChanger::apply($locked, $target->id, $comment, NotifyChoice::emailAlways(), null);

            return [$oldName, $target];
        });

        $order->refresh();

        // The doorway already told the customer. The store still hears who
        // moved it, which is a separate, admin-facing email this class owns.
        self::notifyStore($order, $oldName, $target->name, $comment);

        return $target;
    }

    private static function notifyStore(Order $order, ?string $oldName, string $newName, string $comment): void
    {
        $adminEmail = Setting::where('group', 'mail')->where('key', 'from_address')->value('value');
        if (! $adminEmail) {
            return;
        }
        
        TemplateMailService::send('order_cancelled_by_customer_admin', $adminEmail, [
            'order_id'       => $order->id,
            'customer_name'  => trim($order->first_name . ' ' . $order->last_name),
            'customer_email' => $order->email,
            'order_total'    => store_money($order->total),
            'old_status'     => $oldName ?: 'Unknown',
            'new_status'     => $newName,
            'comment'        => e($comment),
            'store_name'     => (string) config('app.name'),
        ]);
    }
}
