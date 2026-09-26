<?php

namespace Extensions\SmsUnisms;

use Illuminate\Support\Facades\DB;

/**
 * Replace the store's templates with the ones the page posted.
 *
 * The page edits every template at once and saves them together, so the
 * post is the whole truth: a template it no longer carries was deleted.
 */
class SaveSmsTemplates
{
    /** @param list<array{id?: int|null, title: string, message: string, status_id: int}> $templates */
    public function __invoke(array $templates): void
    {
        DB::transaction(function () use ($templates) {
            $kept = array_values(array_filter(array_map(fn ($t) => isset($t['id']) ? (int) $t['id'] : null, $templates)));

            SmsTemplate::whereNotIn('id', $kept)->delete();

            // Statuses are released first and set again below: moving a
            // status from one template to another in the same save would
            // otherwise hold it twice for a moment and trip the unique key.
            SmsTemplate::query()->update(['order_status_id' => null]);

            foreach ($templates as $posted) {
                $template = isset($posted['id']) ? SmsTemplate::findOrFail((int) $posted['id']) : new SmsTemplate();

                $template->fill([
                    'order_status_id' => (int) $posted['status_id'],
                    'title' => trim($posted['title']),
                    // A browser posts a line break as CRLF; a handset counts one character.
                    'message' => rtrim(str_replace(["\r\n", "\r"], "\n", $posted['message'])),
                ])->save();
            }
        });
    }
}
