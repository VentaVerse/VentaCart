<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
    public function sendMigrationResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $storeId = (int) config('app.store_id');
        $customer = Customer::where('store_id', $storeId)
            ->where('email', $request->email)
            ->first();

        if (!$customer || $customer->password !== null) {
            return response()->json(['success' => false, 'message' => 'No migration reset needed.'], 422);
        }

        $status = Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['success' => true, 'message' => 'Password reset link sent to your email.']);
        }

        return response()->json(['success' => false, 'message' => __($status)], 422);
    }
}
