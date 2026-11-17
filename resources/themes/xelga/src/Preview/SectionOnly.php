<?php

namespace Themes\Xelga\Preview;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Themes\Xelga\Documents\Shape;
use Themes\Xelga\XelgaServiceProvider;

/**
 * Answers one section of a preview page instead of the whole page, so the
 * editor can redraw a section after a change that needs the store's data
 * (a new section, a copy, a chosen product or category).
 *
 * The page is rendered in full by the real core controller and the theme's
 * views, from the draft, exactly as the preview frame would show it; only the
 * HTML between the section's comment markers is sent back. Rendering the real
 * page is what keeps every core rule (visibility, prices, stock) in force:
 * there is no second way of drawing a section.
 *
 * It only acts inside the editor preview (PreviewMode decided that already,
 * and runs around this middleware). Anywhere else the parameter is ignored
 * and the ordinary published page is answered.
 */
final class SectionOnly
{
    public const QUERY = 'xelga_section';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->query(self::QUERY);

        if ($id === null || ! PreviewMode::active()) {
            return $next($request);
        }

        if (! is_string($id) || preg_match(Shape::ID, $id) !== 1) {
            return $this->answer('That is not a section id.', 422);
        }

        // Each answer is a full page render, so an editor gets a budget of them.
        $key = 'xelga-section:' . auth('web')->id();
        $perMinute = XelgaServiceProvider::LIMITS['xelga-section'];
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return $this->answer('Too many redraws in a short time.', 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $response = $next($request);

        if ($response->getStatusCode() !== 200 || $response->isRedirection()) {
            return $this->answer('This page did not draw, so the section could not be cut from it.', $response->isRedirection() ? 409 : $response->getStatusCode());
        }

        $html = (string) $response->getContent();
        $open = "<!--xelga:{$id}-->";
        $close = "<!--/xelga:{$id}-->";
        $start = strpos($html, $open);
        $end = $start === false ? false : strpos($html, $close, $start);

        if ($start === false || $end === false) {
            return $this->answer('That section is not drawn on this page.', 404);
        }

        $start += strlen($open);

        return $this->answer(substr($html, $start, $end - $start), 200, $id);
    }

    private function answer(string $body, int $status, ?string $id = null): HttpResponse
    {
        $response = new HttpResponse($body, $status, [
            'Content-Type' => $status === 200 ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
            // A bare HTML fragment: never let a browser guess another type.
            'X-Content-Type-Options' => 'nosniff',
        ]);
        if ($id !== null) {
            $response->headers->set('X-Xelga-Section', $id);
        }

        return $response;
    }
}
