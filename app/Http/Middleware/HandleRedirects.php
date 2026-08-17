<?php

namespace App\Http\Middleware;

use Extensions\Seo\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Looks up the request path in the `redirects` table and short-circuits with
 * a 301/302/410 response when a match exists. Increments hit counters in the
 * background.
 *
 * Path matching is exact (case-sensitive). For pattern-based redirects, an
 * admin can create multiple rows. Query strings on the request are ignored
 * for the lookup; whether they're forwarded to the destination is controlled
 * by the row's `preserve_query` flag.
 */
class HandleRedirects
{
    public function handle(Request $request, Closure $next)
    {
        // Redirect management lives in the seo extension; without it (or
        // before its table exists) this is a straight pass-through.
        if (! class_exists(Redirect::class) || ! Schema::hasTable('redirects')) {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');

        $redirect = Redirect::where('source_path', $path)
            ->where('is_active', true)
            ->first();

        if (! $redirect) {
            return $next($request);
        }

        // Increment counter without triggering Eloquent events / timestamps.
        Redirect::where('id', $redirect->id)->update([
            'hits' => $redirect->hits + 1,
            'last_hit_at' => now(),
        ]);

        if ((int) $redirect->status_code === 410) {
            return response('Gone', 410);
        }

        $destination = $redirect->destination_path;
        if ($redirect->preserve_query && $request->getQueryString()) {
            $destination .= (str_contains($destination, '?') ? '&' : '?') . $request->getQueryString();
        }

        return redirect($destination, $redirect->status_code);
    }
}
