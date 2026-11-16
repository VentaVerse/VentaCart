<?php

namespace App\Support;

/**
 * Who the store's emails come from.
 *
 * The sender is the store itself: the store email set in Settings > General
 * is the From address, and the site name is the From name. Keeping one
 * address for both "how shoppers reach the store" and "who mails them"
 * means a reply to any store email lands where the merchant reads it.
 *
 * When the store has no email yet, the framework's own default (the
 * MAIL_FROM_* values in .env) is left untouched, so a fresh install can
 * still send.
 */
class MailSender
{
    public static function apply(): void
    {
        $address = StoreIdentity::email();

        if ($address === null || filter_var($address, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) === false) {
            return;
        }

        try {
            $name = StoreIdentity::name();
        } catch (\Throwable) {
            // The settings store can be unreachable this early in boot; the
            // application name already carries the site name when it is set.
            $name = (string) config('app.name');
        }

        config([
            'mail.from.address' => $address,
            'mail.from.name' => $name,
        ]);
    }
}
