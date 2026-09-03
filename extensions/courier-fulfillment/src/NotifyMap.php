<?php

namespace Extensions\CourierFulfillment;

use App\Models\Setting;

/**
 * Which order statuses tell the customer, decided once by the merchant
 * instead of every time by whoever is packing.
 *
 * The core order page keeps its unticked box: moving a status there is a
 * deliberate act by someone who has the order open and may have a reason not
 * to write. Fulfilment is the opposite. A packer records twenty parcels an
 * afternoon and should not be deciding, twenty times, whether "Shipped" is
 * the kind of status a customer hears about. That is a policy the store
 * settles once, on this page, and it is the same answer every time.
 *
 * Integrated couriers are unaffected: their own status map already carries a
 * notify flag per courier status, which is a finer-grained answer to the same
 * question and stays the one that wins on their path.
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

    /**
     * The same policy for a notification channel an extension registered.
     *
     * Kept in its own setting per channel rather than folded into one map,
     * so the email policy above keeps its key and its stored value exactly
     * as it was, and a channel that is later removed leaves its answer
     * behind harmlessly instead of corrupting a shared blob.
     *
     * @return list<int>
     */
    public static function allFor(string $channel): array
    {
        return self::read(self::keyFor($channel));
    }

    public static function notifiesVia(string $channel, ?int $statusId): bool
    {
        return $statusId !== null && in_array((int) $statusId, self::allFor($channel), true);
    }

    /** @param  list<int>  $ids */
    public static function putFor(string $channel, array $ids): void
    {
        self::write(self::keyFor($channel), $ids);
    }

    /** Channel keys are short and registered in code, never user input. */
    private static function keyFor(string $channel): string
    {
        return self::KEY . '_' . preg_replace('/[^a-z0-9_]/', '', strtolower($channel));
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
