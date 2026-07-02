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
            'comment'          => 'Courier status: ' . $quadxStatus,
            'notify_customer'  => $target['notify'],
        ]);

        if ($target['notify'] && $order->email) {
            \App\Services\TemplateMailService::send('order_status_update', $order->email, [
                'customer_name' => $order->first_name . ' ' . $order->last_name,
                'order_id'      => $order->id,
                'old_status'    => $oldStatus,
                'new_status'    => $newStatus->name,
                'comment'       => 'Courier status: ' . $quadxStatus,
                'store_name'    => config('app.name'),
            ]);
        }

        return true;
    }
}
