<?php

namespace Extensions\SmsUnisms;

use App\Models\Setting;

/**
 * Which order statuses are worth paying to text about.
 *
 * Kept here rather than in core or in fulfillment because a text costs money
 * per message: the list of statuses worth that belongs beside the account
 * that pays for them, and it must keep working whoever moved the order.
 */
class SmsStatusMap
{
    public const KEY = 'sms_status_ids';

    private const GROUP = 'sms';

    /** @return list<int> */
    public static function all(): array
    {
        $raw = Setting::where('group', self::GROUP)->where('key', self::KEY)->value('value');

        if ($raw === null || $raw === '') {
            return [];
        }

        $ids = json_decode((string) $raw, true);

        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    public static function texts(?int $statusId): bool
    {
        return $statusId !== null && in_array((int) $statusId, self::all(), true);
    }

    /** @param list<int> $ids */
    public static function put(array $ids): void
    {
        Setting::updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode(array_values(array_unique(array_map('intval', $ids))))]
        );
    }
}
