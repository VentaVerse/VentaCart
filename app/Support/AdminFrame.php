<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * An admin page rendered inside another admin page.
 *
 * The layout editor opens a module's own editor in a panel rather than
 * sending the merchant away to it, so that page has to render without the
 * furniture that is already on screen behind it: the slab, the topbar, the
 * command palette. This is NOT a second chrome. It is the one chrome with
 * its furniture off, decided per request, so there is no second template to
 * keep in sync and a module never learns it is being framed.
 */
class AdminFrame
{
    /** The query flag a framing page appends. */
    public const FLAG = 'embed';

    /**
     * The browser is the authority, the flag is the fallback.
     *
     * A query flag alone only survives the first request: the merchant then
     * follows one of the module's own links, or saves and gets redirected,
     * and the flag is gone, so the page inside the panel puts a whole second
     * sidebar back. Sec-Fetch-Dest is sent by the browser on every navigation
     * whose destination is a frame, redirects and form posts included, which
     * is exactly the question being asked. The flag stays for the browsers
     * that do not send it and for tests, which are not browsers at all.
     */
    public static function embedded(?Request $request = null): bool
    {
        $request ??= request();

        if (! $request) {
            return false;
        }

        if ($request->headers->get('Sec-Fetch-Dest') === 'iframe') {
            return true;
        }

        return $request->boolean(self::FLAG);
    }

    /**
     * The same URL, asking to be rendered without furniture.
     *
     * Kept here rather than in JavaScript so the flag's name lives in one
     * place; the editor's panel builds its src from this on the server.
     */
    public static function url(string $url): string
    {
        if (preg_match('/[?&]' . self::FLAG . '=/', $url)) {
            return $url;
        }

        // The fragment stays last or the browser reads the flag as part of it.
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $glue = str_contains($base, '?') ? '&' : '?';
        $base .= $glue . self::FLAG . '=1';

        return $fragment === null ? $base : $base . '#' . $fragment;
    }
}
