<?php

namespace Extensions\CourierFulfillment;

use App\Models\Setting;

/**
 * Which order statuses email the customer by themselves.
 *
 * A merchant decides this once, on Status Mapping, instead of a packer
 * deciding it twenty times an afternoon. It is the store's automatic email
 * list (owner ruling 2026-09-26): fulfilment bookings, courier updates,
 * payments and API calls that do not say all follow it, through
 * FulfillmentEmailPolicy. The core order page keeps its unticked box: a
 * person changing a status by hand decides whether to write.
 *
 * Texts are not decided here. The SMS extension keeps its own templates.
 */
class NotifyMap
{
    public const KEY = 'notify_status_ids';

    private const GROUP = 'courier';

    /** @return list<int> */
    public static function all(): array
    {
        return self::read(self::KEY);
    }

    /** Does moving an order to this status tell the customer? */
    public static function notifies(?int $statusId): bool
    {
        return $statusId !== null && in_array((int) $statusId, self::all(), true);
    }

    /** @param  list<int>  $ids */
    public static function put(array $ids): void
    {
        self::write(self::KEY, $ids);
    }

    /** @return list<int> */
    private static function read(string $key): array
    {
        $raw = Setting::where('group', self::GROUP)->where('key', $key)->value('value');

        if ($raw === null || $raw === '') {
            return [];
        }

        $ids = json_decode((string) $raw, true);

        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    /** @param  list<int>  $ids */
    private static function write(string $key, array $ids): void
    {
        Setting::updateOrCreate(
            ['group' => self::GROUP, 'key' => $key],
            ['value' => json_encode(array_values(array_unique(array_map('intval', $ids))))]
        );
    }
}
