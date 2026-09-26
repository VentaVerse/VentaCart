<?php

namespace Extensions\SmsUnisms;

use Illuminate\Support\Facades\DB;

/**
 * Which order statuses are worth paying to text about, and what each says.
 *
 * A status texts exactly when a template claims it, so there is no second
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
        return DB::table('sms_template_statuses')->pluck('order_status_id')->map(fn ($id) => (int) $id)->all();
    }

    public static function texts(?int $statusId): bool
    {
        return self::messageFor($statusId) !== null;
    }

    /** The template's message for this status, or null when none claims it. */
    public static function messageFor(?int $statusId): ?string
    {
        if ($statusId === null) {
            return null;
        }

        $message = DB::table('sms_template_statuses')
            ->join('sms_templates', 'sms_templates.id', '=', 'sms_template_statuses.sms_template_id')
            ->where('sms_template_statuses.order_status_id', $statusId)
            ->value('sms_templates.message');

        return $message === null ? null : (string) $message;
    }
}
