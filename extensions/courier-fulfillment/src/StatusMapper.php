<?php
namespace Extensions\CourierFulfillment;

use App\Models\OrderHistory;
use App\Models\Setting;

/**
 * Applies the admin-configured QuadX->Venta status map: when a courier status
 * arrives, if it's mapped, move the core order status + record history.
 * resolve() is pure (testable); apply() does the DB write.
 */
class StatusMapper
{
    public static function resolve(array $map, ?string $quadxStatus): ?array
    {
        if ($quadxStatus === null || !isset($map[$quadxStatus])) {
            return null;
        }
        $row = $map[$quadxStatus];
        if (empty($row['order_status_id'])) {
            return null;
        }
        return [
            'order_status_id' => (int) $row['order_status_id'],
            'notify'          => (bool) ($row['notify'] ?? false),
        ];
    }

    public static function currentMap(): array
    {
        $raw = Setting::where('group', 'courier')->where('key', 'quadx_status_map')->value('value');
        $decoded = $raw ? json_decode($raw, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    /** @return bool true if the core order status changed */
    public static function apply(\App\Models\Order $order, ?string $quadxStatus): bool
    {
        $target = self::resolve(self::currentMap(), $quadxStatus);
        if (!$target || (int) $order->order_status_id === $target['order_status_id']) {
            return false;
        }

        $order->update(['order_status_id' => $target['order_status_id']]);
        OrderHistory::create([
            'order_id'         => $order->id,
            'order_status_id'  => $target['order_status_id'],
            'comment'          => 'Courier status: ' . $quadxStatus,
            'notify_customer'  => $target['notify'],
        ]);
        return true;
    }
}
