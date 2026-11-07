<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPasswordResetController extends Controller
{
    public function showForgotForm()
    {
        return view('account.forgot-password');
    }

    public function sendResetLink(Request $request, TurnstileService $turnstile)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        if (!$turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Captcha verification failed. Please try again.',
            ]);
        }

        $status = Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'A password reset link has been sent to your email.')
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('account.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password) {
                $customer->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('customer.login')->with('status', 'Your password has been reset. You can now sign in.')
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * AJAX endpoint: send reset link for migrated customers (called from login modal).
     */
    public function sendMigrationResetLink(Request $request, TurnstileService $turnstile)
    {
        $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        if (!$turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Captcha verification failed. Please try again.',
            ]);
        }

        // One link per email in a window, whatever address asks and whether
        // or not the email has an account, so a held-back request reads the
        // same as a sent one. Keyed on a hash so the cache never holds the
        // email itself.
        $email = mb_strtolower(trim((string) $request->input('email')));
        $minutes = max(1, (int) config('auth.throttle.customer_set_password.per_email_minutes', 15));

        RateLimiter::attempt('customer-set-password:' . hash('sha256', $email), 1, function () use ($request) {
            $customer = Customer::where('store_id', (int) config('app.store_id'))
                ->where('email', $request->input('email'))
                ->whereNull('password')
                ->first();

            // Only an account with no password yet is sent a link; every
            // email gets the same answer, so this cannot find accounts.
            if ($customer) {
                Password::broker('customers')->sendResetLink(['email' => $customer->email]);
            }
        }, $minutes * 60);

        return response()->json([
            'success' => true,
            'message' => 'If that email belongs to an account that needs a new password, a link to set one is on its way.',
        ]);
    }
}
