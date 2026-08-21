<?php

namespace App\Http\Middleware;

use App\Support\AdminFrame;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who may frame the admin, and how a framed page stays framed.
 *
 * Two jobs, one seam.
 *
 * The limit: the layout editor opens a module's own page in a panel, so the
 * admin is deliberately frameable now. Same origin only, which leaves the
 * panel working and takes clickjacking off the table. Scoped to the admin on
 * purpose; a merchant may have good reasons to embed their own storefront
 * somewhere, and that is their call.
 *
 * The continuity: a framed page saves, the controller redirects to a route
 * that knows nothing about frames, and the next page puts a whole second
 * sidebar back inside the panel. Sec-Fetch-Dest would answer this by itself,
 * but browsers only send it from a secure origin, so it is absent on every
 * plain-http development host. The flag is carried across the redirect here
 * instead, where the request that started it is still in hand.
 */
class AdminFraming
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->isAdmin($request)) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");

        if (AdminFrame::embedded($request) && $response->isRedirection()) {
            $location = $response->headers->get('Location');

            if ($location && $this->sameOrigin($request, $location)) {
                $response->headers->set('Location', AdminFrame::url($location));
            }
        }

        return $response;
    }

    private function isAdmin(Request $request): bool
    {
        $prefix = trim((string) config('admin.prefix', 'admin'), '/');

        return $prefix !== '' && ($request->is($prefix) || $request->is($prefix . '/*'));
    }

    /**
     * A redirect off our own host is somebody else's page, and stamping our
     * flag onto it would leak where the merchant was and hand a stranger a
     * parameter we act on.
     */
    private function sameOrigin(Request $request, string $location): bool
    {
        $host = parse_url($location, PHP_URL_HOST);

        return $host === null || $host === $request->getHost();
    }
}
