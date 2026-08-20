<?php

namespace App\Support;

/**
 * Is this storefront request being watched by the theme editor's live preview?
 *
 * Two conditions, and both matter. The request must belong to a signed-in
 * ADMIN, so a shopper can never drive the channel by adding a query flag. And
 * the preview must have been asked for, so an admin's ordinary visit to their
 * own storefront does not carry the editor's script.
 *
 * The window is held in the SESSION rather than read from the query string on
 * every request, because the query string does not survive a redirect. The
 * bench is meant to be browsed: click Account while signed out and the
 * storefront sends you to the login page, and that redirect drops ?__preview=1.
 * The frame would then load a page with no preview script, the editor would
 * never hear from it, and the controls would silently keep describing the page
 * you just left. It looked like the preview worked and lied about what it
 * showed, which is worse than not working.
 *
 * The window expires so the flag cannot outlive the session that opened it.
 * While it is open the script is inert unless framed: it reports nothing to a
 * parent that is itself, and it only picks when the editor asks it to.
 */
class ThemePreview
{
    public const SESSION_KEY = 'theme_preview_until';

    /** How long one bench session keeps the storefront in preview mode. */
    public const WINDOW_MINUTES = 120;

    public static function active(): bool
    {
        if (! auth('web')->check()) {
            return false;
        }

        if (request()->boolean('__preview')) {
            session([self::SESSION_KEY => now()->addMinutes(self::WINDOW_MINUTES)->timestamp]);

            return true;
        }

        return (int) session(self::SESSION_KEY, 0) > now()->timestamp;
    }

    /** Leaving the bench closes the window; nothing waits for it to expire. */
    public static function close(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
