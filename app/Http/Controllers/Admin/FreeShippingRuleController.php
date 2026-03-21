<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\FreeShippingRule;
use App\Models\GeoZone;
use Illuminate\Http\Request;

class FreeShippingRuleController extends Controller
{
    public function index()
    {
        $rules = FreeShippingRule::orderBy('sort_order')->get();
        return view('admin.free-shipping-rules.index', compact('rules'));
    }

    public function create()
    {
        return view('admin.free-shipping-rules.form', [
            'rule' => null,
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
            'shippingZones' => GeoZone::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'brand_ids' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'customer_group_ids' => ['nullable', 'array'],
            'shipping_zone_ids' => ['nullable', 'array'],
            'exclude_brand_ids' => ['nullable', 'array'],
            'exclude_category_ids' => ['nullable', 'array'],
            'exclude_customer_group_ids' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'apply_whole_cart' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['apply_whole_cart'] = $request->boolean('apply_whole_cart');

        // Clean empty arrays to null
        foreach (['brand_ids', 'category_ids', 'customer_group_ids', 'shipping_zone_ids', 'exclude_brand_ids', 'exclude_category_ids', 'exclude_customer_group_ids'] as $field) {
            if (empty($validated[$field])) {
                $validated[$field] = null;
            }
        }

        FreeShippingRule::create($validated);

        return redirect()->route('admin.free-shipping-rules.index')
            ->with('success', 'Free shipping rule created.');
    }

    public function edit(FreeShippingRule $freeShippingRule)
    {
        return view('admin.free-shipping-rules.form', [
            'rule' => $freeShippingRule,
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
            'shippingZones' => GeoZone::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, FreeShippingRule $freeShippingRule)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'brand_ids' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'customer_group_ids' => ['nullable', 'array'],
            'shipping_zone_ids' => ['nullable', 'array'],
            'exclude_brand_ids' => ['nullable', 'array'],
            'exclude_category_ids' => ['nullable', 'array'],
            'exclude_customer_group_ids' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');

        foreach (['brand_ids', 'category_ids', 'customer_group_ids', 'shipping_zone_ids', 'exclude_brand_ids', 'exclude_category_ids', 'exclude_customer_group_ids'] as $field) {
            if (empty($validated[$field])) {
                $validated[$field] = null;
            }
        }

        $freeShippingRule->update($validated);

        return redirect()->route('admin.free-shipping-rules.index')
            ->with('success', 'Free shipping rule updated.');
    }

    public function destroy(FreeShippingRule $freeShippingRule)
    {
        $freeShippingRule->delete();

        return redirect()->route('admin.free-shipping-rules.index')
            ->with('success', 'Free shipping rule deleted.');
    }

    public function toggleActive(FreeShippingRule $freeShippingRule)
    {
        $freeShippingRule->update(['is_active' => !$freeShippingRule->is_active]);

        return back()->with('success', 'Rule ' . ($freeShippingRule->is_active ? 'enabled' : 'disabled') . '.');
    }
}
