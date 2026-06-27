<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Absolute session cap for the admin (web) guard: forces re-login once
 * now - web_login_at exceeds auth.session_policy.admin.absolute_cap_minutes,
 * regardless of activity or remember-me. Anchored on the user's stored login
 * timestamp so it can't be slid by returning before the session idles out.
 */
class EnforceAdminSessionCap
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user) {
            $cap = (int) config('auth.session_policy.admin.absolute_cap_minutes', 1440);
            $loginAt = $user->web_login_at;

            // Expired when the login happened at/before (now - cap).
            if ($cap > 0 && $loginAt && $loginAt->lte(now()->subMinutes($cap))) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $hours = rtrim(rtrim(number_format($cap / 60, 1), '0'), '.');

                return redirect()->guest(route('login'))
                    ->with('status', "Your session expired after {$hours}h. Please sign in again.");
            }
        }

        return $next($request);
    }
}
