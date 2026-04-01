<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\GeoZone;
use App\Models\WeightBasedMethod;
use Illuminate\Http\Request;

class WeightBasedMethodController extends Controller
{
    public function create()
    {
        return view('admin.weight-based-methods.form', [
            'method' => null,
            'geoZones' => GeoZone::where('is_active', true)->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateMethod($request);
        $method = WeightBasedMethod::create($validated);
        $this->syncRates($method, $request->input('rates', []));

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'weight-based'])
            ->with('success', 'Weight-based method created.');
    }

    public function edit(WeightBasedMethod $weightBasedMethod)
    {
        return view('admin.weight-based-methods.form', [
            'method' => $weightBasedMethod->load('rates'),
            'geoZones' => GeoZone::where('is_active', true)->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, WeightBasedMethod $weightBasedMethod)
    {
        $validated = $this->validateMethod($request);
        $weightBasedMethod->update($validated);
        $this->syncRates($weightBasedMethod, $request->input('rates', []));

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'weight-based'])
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
                'geo_zone_id' => !empty($data['geo_zone_id']) ? $data['geo_zone_id'] : null,
                'customer_group_id' => !empty($data['customer_group_id']) ? $data['customer_group_id'] : null,
                'is_active' => isset($data['is_active']),
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncRates($method, $data['rates'] ?? []);
        }

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'weight-based'])
            ->with('success', count($methods) . ' method(s) updated.');
    }

    public function destroy(WeightBasedMethod $weightBasedMethod)
    {
        $weightBasedMethod->delete();

        return redirect()->route('admin.shipping-settings.index', ['tab' => 'weight-based'])
            ->with('success', 'Weight-based method deleted.');
    }

    private function validateMethod(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'geo_zone_id' => ['nullable', 'exists:geo_zones,id'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['geo_zone_id'] = $validated['geo_zone_id'] ?: null;
        $validated['customer_group_id'] = $validated['customer_group_id'] ?: null;

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
