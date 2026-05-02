<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Marks the response as noindex so Google won't include the page in its index.
 *
 * Apply to private/utility/auth route groups (cart, checkout, account, search
 * results, password reset) so search engines don't index these — they're either
 * private to the user, transactional, or thin (e.g. duplicates of category
 * pages with different sort options).
 *
 * Sets both:
 *   - The `robotsMeta` view variable, picked up by the storefront layout's
 *     `<meta name="robots">` tag.
 *   - An `X-Robots-Tag` HTTP header for non-HTML responses (PDFs, JSON, etc.).
 *
 * Default `noindex, nofollow`. Pass `follow` as the middleware argument for
 * pages where you still want Google to crawl outbound links (e.g. search
 * results — they're noindex but their product links should still be followed).
 *
 *   Route::get(...)->middleware('noindex');           // noindex, nofollow
 *   Route::get(...)->middleware('noindex:follow');    // noindex, follow
 */
class NoIndexResponse
{
    public function handle(Request $request, Closure $next, string $followBehavior = 'nofollow')
    {
        $directives = "noindex, {$followBehavior}";

        View::share('robotsMeta', $directives);

        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-Robots-Tag', $directives);
        }

        return $response;
    }
}
