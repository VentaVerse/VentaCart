<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function dashboard()
    {
        $customer = Auth::guard('customer')->user();
        $recentOrders = $customer->orders()->visibleToCustomer()->latest()->take(5)->get();
        $defaultAddress = $customer->defaultAddress();

        return view('account.dashboard', compact('customer', 'recentOrders', 'defaultAddress'));
    }

    public function editProfile()
    {
        $customer = Auth::guard('customer')->user();
        return view('account.profile', compact('customer'));
    }

    public function updateProfile(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'newsletter' => ['nullable', 'boolean'],
        ]);

        $validated['newsletter'] = $request->boolean('newsletter');

        $customer->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->current_password, $customer->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $customer->update(['password' => $request->password]);

        return back()->with('success', 'Password changed successfully.');
    }
}
