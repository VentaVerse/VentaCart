<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerLoginRequest;
use App\Http\Requests\Storefront\CustomerRegisterRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Services\CartService;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('account.dashboard');
        }

        $redirect = $request->query('redirect');

        return view('account.login', compact('redirect'));
    }

    public function login(CustomerLoginRequest $request, TurnstileService $turnstile)
    {
        if (!$turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Captcha verification failed. Please try again.',
            ]);
        }

        $oldSessionId = session()->getId();
        $storeId = (int) config('app.store_id');

        // Check if this is a migrated customer with no password set
        $customer = Customer::where('store_id', $storeId)
            ->where('email', $request->email)
            ->first();

        if ($customer && $customer->password === null) {
            throw ValidationException::withMessages([
                'needs_reset' => $customer->email,
            ]);
        }

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
            'store_id' => $storeId,
            'is_active' => true,
        ];

        if (!Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();

        app(CartService::class)->migrateGuestCart($oldSessionId, Auth::guard('customer')->user());

        if ($request->input('redirect') === 'checkout') {
            return redirect()->route('checkout.index');
        }

        return redirect()->intended(route('account.dashboard'));
    }

    public function showRegister(Request $request)
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('account.dashboard');
        }

        $redirect = $request->query('redirect');

        return view('account.register', compact('redirect'));
    }

    public function register(CustomerRegisterRequest $request, TurnstileService $turnstile)
    {
        if (!$turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Captcha verification failed. Please try again.',
            ]);
        }

        $oldSessionId = session()->getId();

        $storeId = (int) config('app.store_id');

        $defaultGroup = CustomerGroup::where('store_id', $storeId)
            ->where('is_default', true)
            ->first();

        $customer = Customer::create([
            'store_id' => $storeId,
            'customer_group_id' => $defaultGroup?->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'newsletter' => $request->boolean('newsletter'),
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        app(CartService::class)->migrateGuestCart($oldSessionId, $customer);

        \App\Services\TemplateMailService::send('welcome', $customer->email, [
            'customer_name' => $customer->first_name . ' ' . $customer->last_name,
            'store_name' => config('app.name'),
            'store_url' => config('app.url'),
        ]);

        if ($request->input('redirect') === 'checkout') {
            return redirect()->route('checkout.index');
        }

        return redirect()->route('account.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
