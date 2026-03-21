<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerGroup::withCount('customers');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        $groups = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.customer-groups.index', compact('groups'));
    }

    public function create()
    {
        return view('admin.customer-groups.form', [
            'group' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            CustomerGroup::where('store_id', $validated['store_id'])->update(['is_default' => false]);
        }

        CustomerGroup::create($validated);

        return redirect()->route('admin.customer-groups.index')
            ->with('success', 'Customer group created successfully.');
    }

    public function edit(CustomerGroup $customerGroup)
    {
        return view('admin.customer-groups.form', [
            'group' => $customerGroup,
        ]);
    }

    public function update(Request $request, CustomerGroup $customerGroup)
    {
        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            CustomerGroup::where('store_id', $validated['store_id'])
                ->where('id', '!=', $customerGroup->id)
                ->update(['is_default' => false]);
        }

        $customerGroup->update($validated);

        return redirect()->route('admin.customer-groups.index')
            ->with('success', 'Customer group updated successfully.');
    }

    public function destroy(CustomerGroup $customerGroup)
    {
        if ($customerGroup->customers()->count() > 0) {
            return redirect()->route('admin.customer-groups.index')
                ->with('error', 'Cannot delete a group that has customers. Move them first.');
        }

        $customerGroup->delete();

        return redirect()->route('admin.customer-groups.index')
            ->with('success', 'Customer group deleted. Customers moved to default group.');
    }
}
