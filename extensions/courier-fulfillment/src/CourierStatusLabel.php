<?php

namespace Extensions\CourierFulfillment;

use App\Models\Setting;

/**
 * The words a courier status is shown in, anywhere a person reads it:
 * the cached "{courier}_status_labels" the admin refreshed from the
 * courier, then a courier's own pre-known vocabulary (statusLabels(), where
 * a courier has one), then the raw code with its underscores spaced and its
 * first letter raised. The raw code itself stays on the order
 * (courier_status) for the admin and the API.
 */
final class CourierStatusLabel
{
    /** @var array<string, array<string, string>> */
    private static array $labels = [];

    public static function for(string $courierKey, string $status): string
    {
        $labels = self::labels($courierKey);

        if (isset($labels[$status]) && is_string($labels[$status]) && $labels[$status] !== '') {
            return $labels[$status];
        }

        return ucfirst(str_replace(['_', '-'], ' ', $status));
    }

    /**
     * "{Courier}: {label}", the sentence a history row and the customer's
     * status email carry. The courier's name comes from the order's own
     * snapshot when it has one, so a renamed or removed courier keeps the
     * name the parcel actually travelled under.
     */
    public static function sentence(\App\Models\Order $order, string $status): string
    {
        $key = (string) $order->courier_provider;
        $name = $order->shipping_courier_name ?: (CourierManager::get($key)?->getName() ?? ucfirst($key));

        return $name . ': ' . self::for($key, $status);
    }

    /** @return array<string, string> */
    private static function labels(string $courierKey): array
    {
        if (isset(self::$labels[$courierKey])) {
            return self::$labels[$courierKey];
        }

        $raw = Setting::where('group', 'courier')->where('key', $courierKey . '_status_labels')->value('value');
        $cached = $raw ? json_decode($raw, true) : [];
        $cached = is_array($cached) ? $cached : [];

        $courier = CourierManager::get($courierKey);
        $known = ($courier && method_exists($courier, 'statusLabels')) ? (array) $courier->statusLabels() : [];

        return self::$labels[$courierKey] = $cached + $known;
    }

    public static function forget(): void
    {
        self::$labels = [];
    }
}
