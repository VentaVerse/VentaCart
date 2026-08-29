<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A customer cancels their own order from their account, while it still
 * sits on a status the merchant marked "customer may cancel" (the unpaid
 * ones, in practice). Owner request, 2026-08-30.
 *
 * The rule is data, not code: OrderStatus::customer_can_cancel per status,
 * and setting checkout.customer_cancel_status_id for where it lands. No
 * landing status chosen, no cancellation offered. Because the move goes
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
    public const SETTING_GROUP = 'checkout';
    public const SETTING_KEY = 'customer_cancel_status_id';

    /** Can this customer cancel this order right now? Read-only; the page uses it to show the button. */
    public static function allowed(Order $order, ?Customer $customer): bool
    {
        if (! $customer || (int) $order->customer_id !== (int) $customer->id) {
            return false;
        }

        return (bool) $order->status?->customer_can_cancel && self::target($order) !== null;
    }

    /**
     * The status a customer cancellation lands on. Null when the merchant
     * has not chosen one, and then nothing is offered: no guessing by a
     * status NAME (owner ruling, 2026-08-30: a name can be renamed or
     * deleted, and a guess that silently stops working is worse than a
     * button that never appears until the setting is made).
     */
    public static function target(Order $order): ?OrderStatus
    {
        $id = Setting::where('group', self::SETTING_GROUP)->where('key', self::SETTING_KEY)->value('value');

        return $id ? OrderStatus::where('store_id', $order->store_id)->find((int) $id) : null;
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

            OrderStatusChanger::apply($locked, $target->id, $comment, true, null);

            return [$oldName, $target];
        });

        $order->refresh();

        // After the commit, the way every status change notifies: the
        // customer hears the order moved, the store hears who moved it.
        OrderNotificationService::statusChanged($order, $oldName, $target->name, $comment);
        self::notifyStore($order, $oldName, $target->name, $comment);

        return $target;
    }

    private static function notifyStore(Order $order, ?string $oldName, string $newName, string $comment): void
    {
        $adminEmail = Setting::where('group', 'mail')->where('key', 'from_address')->value('value');
        if (! $adminEmail) {
            return;
        }
        $currency = Setting::where('key', 'currency_symbol')->value('value') ?? '₱';

        TemplateMailService::send('order_cancelled_by_customer_admin', $adminEmail, [
            'order_id'       => $order->id,
            'customer_name'  => trim($order->first_name . ' ' . $order->last_name),
            'customer_email' => $order->email,
            'order_total'    => $currency . number_format((float) $order->total, 2),
            'old_status'     => $oldName ?: 'Unknown',
            'new_status'     => $newName,
            'comment'        => e($comment),
            'store_name'     => (string) config('app.name'),
        ]);
    }
}
