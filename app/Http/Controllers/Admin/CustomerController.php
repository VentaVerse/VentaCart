<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with(['customerGroup']);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('customer_group_id')) {
            $query->where('customer_group_id', $request->input('customer_group_id'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  // Full-name search: "John Doe" matches first+last together.
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $customerGroups = CustomerGroup::orderBy('name')->get();

        return view('admin.customers.index', compact('customers', 'customerGroups'));
    }

    public function create()
    {
        $customerGroups = CustomerGroup::orderBy('name')->get();
        $countries = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.customers.form', [
            'customer' => null,
            'customerGroups' => $customerGroups,
            'countries' => $countries,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCustomer($request);
        $validated['password'] = Hash::make($validated['password']);

        $customer = Customer::create($validated);

        $this->syncAddresses($customer, $request);

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function edit(Customer $customer)
    {
        $customer->load('addresses', 'activities');
        $customerGroups = CustomerGroup::orderBy('name')->get();
        $countries = Country::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.customers.form', compact('customer', 'customerGroups', 'countries'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $this->validateCustomer($request, $customer->id);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $customer->update($validated);

        $this->syncAddresses($customer, $request);

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customer->addresses()->delete();
        $customer->cart()?->first()?->items()->delete();
        $customer->cart()?->delete();
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    protected function validateCustomer(Request $request, ?int $customerId = null): array
    {
        $rules = [
            'store_id' => ['required', 'exists:stores,id'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('customers')->where('store_id', $request->input('store_id'))->ignore($customerId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'newsletter' => ['boolean'],
        ];

        if ($customerId) {
            $rules['password'] = ['nullable', 'string', 'min:8'];
        } else {
            $rules['password'] = ['required', 'string', 'min:8'];
        }

        return $request->validate($rules);
    }

    protected function syncAddresses(Customer $customer, Request $request): void
    {
        $addressesData = $request->input('addresses', []);
        $keepIds = [];

        foreach ($addressesData as $data) {
            if (empty($data['address_1'])) {
                continue;
            }

            $addressData = [
                'first_name' => $data['first_name'] ?? $customer->first_name,
                'last_name' => $data['last_name'] ?? $customer->last_name,
                'address_1' => $data['address_1'],
                'city' => $data['city'] ?? '',
                'district' => $data['district'] ?? null,
                'barangay' => $data['barangay'] ?? null,
                'state' => $data['state'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country_id' => $data['country_id'] ?? null,
                'is_default' => !empty($data['is_default']),
            ];

            if (!empty($data['id'])) {
                $address = $customer->addresses()->find($data['id']);
                if ($address) {
                    $address->update($addressData);
                    $keepIds[] = $address->id;
                }
            } else {
                $address = $customer->addresses()->create($addressData);
                $keepIds[] = $address->id;
            }
        }

        // Delete removed addresses
        $customer->addresses()->whereNotIn('id', $keepIds)->delete();

        // Ensure exactly one default if addresses exist
        if ($customer->addresses()->count() > 0 && !$customer->addresses()->where('is_default', true)->exists()) {
            $customer->addresses()->oldest()->first()->update(['is_default' => true]);
        }
    }
}
