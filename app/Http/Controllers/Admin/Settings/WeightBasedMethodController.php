<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\GeoZone;
use App\Models\WeightBasedMethod;
use Illuminate\Http\Request;

class WeightBasedMethodController extends Controller
{
    public function create()
    {
        return view('admin.settings.shipping.weight-based-methods.form', [
            'method' => null,
            'geoZones' => GeoZone::where('is_active', true)->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateMethod($request);
        $method = WeightBasedMethod::create($validated);
        $method->geoZones()->sync($request->input('geo_zone_ids', []));
        $method->customerGroups()->sync($request->input('customer_group_ids', []));
        $this->syncRates($method, $request->input('rates', []));

        return redirect()->route('admin.shipping-settings.weight-based')
            ->with('success', 'Weight-based method created.');
    }

    public function edit(WeightBasedMethod $weightBasedMethod)
    {
        return view('admin.settings.shipping.weight-based-methods.form', [
            'method' => $weightBasedMethod->load(['rates', 'geoZones', 'customerGroups']),
            'geoZones' => GeoZone::where('is_active', true)->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, WeightBasedMethod $weightBasedMethod)
    {
        $validated = $this->validateMethod($request);
        $weightBasedMethod->update($validated);
        $weightBasedMethod->geoZones()->sync($request->input('geo_zone_ids', []));
        $weightBasedMethod->customerGroups()->sync($request->input('customer_group_ids', []));
        $this->syncRates($weightBasedMethod, $request->input('rates', []));

        return redirect()->route('admin.shipping-settings.weight-based')
            ->with('success', 'Weight-based method updated.');
    }

    public function bulkUpdate(Request $request)
    {
        $methods = $request->input('methods', []);

        foreach ($methods as $id => $data) {
            $method = WeightBasedMethod::find($id);
            if (!$method) continue;

            $method->update([
                'name' => $data['name'] ?? $method->name,
                'admin_name' => $data['admin_name'] ?? $method->admin_name,
                'is_active' => isset($data['is_active']),
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $method->geoZones()->sync($data['geo_zone_ids'] ?? []);
            $method->customerGroups()->sync($data['customer_group_ids'] ?? []);
            $this->syncRates($method, $data['rates'] ?? []);
        }

        return redirect()->route('admin.shipping-settings.weight-based')
            ->with('success', count($methods) . ' method(s) updated.');
    }

    public function duplicate(WeightBasedMethod $weightBasedMethod)
    {
        $copy = $weightBasedMethod->replicate();
        $copy->admin_name = ($weightBasedMethod->admin_name ?: $weightBasedMethod->name) . ' (Copy)';
        $copy->save();

        $copy->geoZones()->sync($weightBasedMethod->geoZones->pluck('id'));
        $copy->customerGroups()->sync($weightBasedMethod->customerGroups->pluck('id'));

        foreach ($weightBasedMethod->rates as $rate) {
            $copy->rates()->create($rate->only(['weight_from', 'weight_to', 'cost']));
        }

        return redirect()->route('admin.shipping-settings.weight-based')
            ->with('success', 'Method duplicated as "' . $copy->admin_name . '".');
    }

    public function destroy(WeightBasedMethod $weightBasedMethod)
    {
        $weightBasedMethod->delete();

        return redirect()->route('admin.shipping-settings.weight-based')
            ->with('success', 'Weight-based method deleted.');
    }

    private function validateMethod(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'admin_name' => ['nullable', 'string', 'max:255'],
            'geo_zone_ids' => ['nullable', 'array'],
            'geo_zone_ids.*' => ['exists:geo_zones,id'],
            'customer_group_ids' => ['nullable', 'array'],
            'customer_group_ids.*' => ['exists:customer_groups,id'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['admin_name'] = $validated['admin_name'] ?: $validated['name'];

        // Remove pivot fields from fillable data
        unset($validated['geo_zone_ids'], $validated['customer_group_ids']);

        return $validated;
    }

    private function syncRates(WeightBasedMethod $method, array $rates): void
    {
        $method->rates()->delete();

        foreach ($rates as $rate) {
            if (empty($rate['weight_to'])) continue;

            $method->rates()->create([
                'weight_from' => $rate['weight_from'] ?? 0,
                'weight_to' => $rate['weight_to'],
                'cost' => $rate['cost'] ?? 0,
            ]);
        }
    }
}
