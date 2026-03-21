<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Customer $customer)
    {
        session()->put('impersonating_customer_id', $customer->id);
        session()->put('impersonate_return_url', route('admin.customers.edit', $customer));

        Auth::guard('customer')->login($customer);

        return redirect()->route('home');
    }

    public function stop()
    {
        $returnUrl = session()->pull('impersonate_return_url', route('admin.customers.index'));
        session()->forget('impersonating_customer_id');

        Auth::guard('customer')->logout();

        // Validate return URL is internal to prevent open redirect
        $baseUrl = rtrim(config('app.url'), '/');
        if (!str_starts_with($returnUrl, $baseUrl)) {
            $returnUrl = route('admin.customers.index');
        }

        return redirect($returnUrl);
    }
}
