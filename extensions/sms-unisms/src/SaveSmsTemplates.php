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
    /** @param list<array{id?: int|null, title: string, message: string, status_ids: list<int>}> $templates */
    public function __invoke(array $templates): void
    {
        DB::transaction(function () use ($templates) {
            $kept = array_values(array_filter(array_map(fn ($t) => isset($t['id']) ? (int) $t['id'] : null, $templates)));

            // Assignments go first and come back fresh below: moving a status
            // from one template to another in the same save would otherwise
            // hold it in both for a moment and trip the unique key.
            DB::table('sms_template_statuses')->delete();
            SmsTemplate::whereNotIn('id', $kept)->delete();

            foreach ($templates as $posted) {
                $template = isset($posted['id']) ? SmsTemplate::findOrFail((int) $posted['id']) : new SmsTemplate();

                $template->fill([
                    'title' => trim($posted['title']),
                    // A browser posts a line break as CRLF; a handset counts one character.
                    'message' => rtrim(str_replace(["\r\n", "\r"], "\n", $posted['message'])),
                ])->save();

                $template->statuses()->sync(array_values(array_unique(array_map('intval', $posted['status_ids']))));
            }
        });
    }
}
