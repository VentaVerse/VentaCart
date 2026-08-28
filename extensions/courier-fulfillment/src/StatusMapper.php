<?php
namespace Extensions\CourierFulfillment;

use App\Models\OrderHistory;
use App\Models\Setting;

/**
 * Applies the admin-configured courier->Venta status map: when a courier
 * status arrives, if it's mapped, move the core order status + record
 * history. resolve() is pure (testable); apply() does the DB write.
 *
 * The map is stored per courier under settings key "{courier_key}_status_map"
 * — every courier already shipped keeps the exact storage key its own
 * getKey() always produced, so this change is a pure derivation and touches
 * no stored data. apply() derives the courier key from the order itself
 * (orders.courier_provider, set at booking time), so every existing call
 * site keeps working unchanged.
 */
class StatusMapper
{
    public static function resolve(array $map, ?string $courierStatus): ?array
    {
        if ($courierStatus === null || !isset($map[$courierStatus])) {
            return null;
        }
        $row = $map[$courierStatus];
        if (empty($row['order_status_id'])) {
            return null;
        }
        return [
            'order_status_id' => (int) $row['order_status_id'],
            'notify'          => (bool) ($row['notify'] ?? false),
        ];
    }

    /** @param string $courierKey a registered courier's key — see CourierFulfillmentInterface::getKey(). */
    public static function currentMap(string $courierKey): array
    {
        $raw = Setting::where('group', 'courier')->where('key', "{$courierKey}_status_map")->value('value');
        $decoded = $raw ? json_decode($raw, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    /** @return bool true if the core order status changed */
    public static function apply(\App\Models\Order $order, ?string $courierStatus): bool
    {
        $courierKey = (string) $order->courier_provider;
        $target = self::resolve(self::currentMap($courierKey), $courierStatus);
        if (!$target || (int) $order->order_status_id === $target['order_status_id']) {
            return false;
        }

        $newStatus = \App\Models\OrderStatus::find($target['order_status_id']);
        if (!$newStatus) {
            return false;
        }

        $oldStatus = \App\Models\OrderStatus::find($order->order_status_id)?->name ?? 'Unknown';

        $order->update(['order_status_id' => $newStatus->id]);
        \App\Services\StockService::adjustForStatusChange($order, $newStatus);

        OrderHistory::create([
            'order_id'         => $order->id,
            'order_status_id'  => $newStatus->id,
            'comment'          => 'Courier status: ' . $courierStatus,
            'notify_customer'  => $target['notify'],
        ]);

        // Through the shared sender, so the email names the courier and links
        // the tracking number this order has carried since booking. It used
        // to send the bare status pair and nothing to click.
        if ($target['notify']) {
            \App\Services\OrderNotificationService::statusChanged($order, $oldStatus, $newStatus->name, 'Courier status: ' . $courierStatus);
        }

        return true;
    }
}
