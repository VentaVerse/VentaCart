<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks every admin request against the permission map
 * (App\Support\AdminPermissions, config/admin-permissions.php).
 *
 * It runs in the 'web' group rather than on core's admin route group alone,
 * because extensions and themes register admin routes of their own outside
 * that group; every one of them must be checked, and one nobody mapped is
 * super admin only. A route counts as an admin route when its name starts
 * with "admin." or it carries the admin session middleware.
 *
 * A guest is passed on untouched: the route's own 'auth' middleware sends
 * them to sign in. The priority list (bootstrap/app.php) runs this after
 * authentication and before route model binding, so a refused request
 * never loads the records it named.
 */
class EnforceAdminPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if (! $route instanceof Route || ! $this->isAdminRoute($route)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (app(AdminPermissions::class)->allows($user, $route->getName(), $request->getMethod())) {
            return $next($request);
        }

        $message = 'You do not have permission to do this. Ask a super admin for access.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
            ], 403);
        }

        abort(403, $message);
    }

    private function isAdminRoute(Route $route): bool
    {
        if (str_starts_with((string) $route->getName(), 'admin.')) {
            return true;
        }

        return in_array('admin.session.cap', $route->gatherMiddleware(), true)
            || in_array(EnforceAdminSessionCap::class, $route->gatherMiddleware(), true);
    }
}
