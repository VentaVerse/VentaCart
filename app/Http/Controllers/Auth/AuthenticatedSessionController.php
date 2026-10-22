<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View|RedirectResponse
    {
        // A fresh install has no admin to sign in as: make one first.
        if (! \App\Models\User::exists()) {
            return redirect()->route('setup');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->enforceSingleSession($request);

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    /**
     * Admin policy: one active session per account.
     *  - Rotate the shared remember token so any *other* device's remember
     *    cookie can no longer re-authenticate; re-issue this device's cookie
     *    with the new token (only if "remember me" was used).
     *  - Delete every other session row for this user (database driver).
     *  - Stamp web_login_at for the absolute session cap.
     */
    protected function enforceSingleSession(Request $request): void
    {
        $guard = Auth::guard('web');
        $user = $guard->user();
        $remembered = $request->boolean('remember');

        // Rotate the remember token -> invalidates every other device's cookie.
        $newToken = Str::random(60);
        $user->forceFill([
            'remember_token' => $newToken,
            'web_login_at' => now(),
        ])->save();

        // Re-issue THIS device's recaller with the new token (after the rotation,
        // or we'd lock ourselves out on the next request).
        if ($remembered) {
            $minutes = (int) config('auth.session_policy.admin.remember_minutes', 1440);
            Cookie::queue(
                $guard->getRecallerName(),
                $user->getAuthIdentifier().'|'.$newToken.'|'.$user->getAuthPassword(),
                $minutes
            );
        }

        // Drop all other session rows for this admin (single active session).
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
