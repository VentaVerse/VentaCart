<?php

namespace Extensions\SmsUnisms;

use Illuminate\Support\Facades\DB;

/**
 * Which order statuses are worth paying to text about, and what each says.
 *
 * A status texts exactly when a template is written for it, so there is no second
 * list of ticks to fall out of step with the messages. Kept here rather than
 * in core because a text costs money per message: the choice belongs beside
 * the account that pays for it, and it must keep working whoever moved the
 * order.
 */
class SmsStatusMap
{
    /** @return list<int> */
    public static function all(): array
    {
        return DB::table('sms_templates')->whereNotNull('order_status_id')->where('is_active', true)
            ->pluck('order_status_id')->map(fn ($id) => (int) $id)->all();
    }

    public static function texts(?int $statusId): bool
    {
        return self::messageFor($statusId) !== null;
    }

    /** The template's message for this status, or null when none is written for it or it is switched off. */
    public static function messageFor(?int $statusId): ?string
    {
        return self::templateFor($statusId)?->message;
    }

    /** The switched-on template for this status, or null. */
    public static function templateFor(?int $statusId): ?SmsTemplate
    {
        if ($statusId === null) {
            return null;
        }

        return SmsTemplate::where('order_status_id', $statusId)->where('is_active', true)->first();
    }
}
