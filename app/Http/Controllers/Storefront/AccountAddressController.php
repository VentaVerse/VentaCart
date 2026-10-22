<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Services\Address\DeepAddressRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountAddressController extends Controller
{
    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $addresses = $customer->addresses()->with('country')->latest()->get();

        return view('account.addresses', compact('addresses'));
    }

    public function create()
    {
        $countries = Country::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'iso2', 'name']);

        $address = null;

        return view('account.address-form', compact('countries', 'address'));
    }

    public function store(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        $validated = $this->validateAddress($request);

        $isFirst = $customer->addresses()->count() === 0;
        if ($isFirst || $request->boolean('is_default')) {
            $customer->addresses()->update(['is_default' => false]);
            $validated['is_default'] = true;
        }

        $customer->addresses()->create($validated);

        return redirect()->route('account.addresses')->with('success', 'Address added successfully.');
    }

    public function edit(CustomerAddress $address)
    {
        abort_if($address->customer_id !== Auth::guard('customer')->id(), 403);

        $countries = Country::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'iso2', 'name']);

        return view('account.address-form', compact('countries', 'address'));
    }

    public function update(Request $request, CustomerAddress $address)
    {
        $customer = Auth::guard('customer')->user();
        abort_if($address->customer_id !== $customer->id, 403);

        $validated = $this->validateAddress($request);

        if ($request->boolean('is_default')) {
            $customer->addresses()->update(['is_default' => false]);
            $validated['is_default'] = true;
        }

        $address->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'address' => $address->fresh(),
                'addresses' => $customer->addresses()->latest()->get(),
            ]);
        }

        return redirect()->route('account.addresses')->with('success', 'Address updated successfully.');
    }

    public function destroy(CustomerAddress $address)
    {
        abort_if($address->customer_id !== Auth::guard('customer')->id(), 403);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = Auth::guard('customer')->user()->addresses()->latest()->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return redirect()->route('account.addresses')->with('success', 'Address deleted.');
    }

    public function setDefault(CustomerAddress $address)
    {
        abort_if($address->customer_id !== Auth::guard('customer')->id(), 403);

        $customer = Auth::guard('customer')->user();
        $customer->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return redirect()->route('account.addresses')->with('success', 'Default address updated.');
    }

    private function validateAddress(Request $request): array
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address_1' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        app(DeepAddressRules::class)->validate($validated);

        return $validated;
    }
}
