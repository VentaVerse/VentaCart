<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerLoginRequest;
use App\Http\Requests\Storefront\CustomerRegisterRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Mail\WelcomeMail;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    public function login(CustomerLoginRequest $request)
    {
        $oldSessionId = session()->getId();

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
            'store_id' => (int) config('app.store_id'),
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

    public function register(CustomerRegisterRequest $request)
    {
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

        try {
            Mail::to($customer->email)->send(new WelcomeMail($customer));
        } catch (\Throwable $e) {
            Log::warning('Welcome email failed: ' . $e->getMessage());
        }

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
