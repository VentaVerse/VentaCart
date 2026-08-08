<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ShippingCourier;
use Illuminate\Http\Request;

class ShippingCourierController extends Controller
{
    private int $storeId;

    public function __construct()
    {
        $this->storeId = (int) config('app.store_id', 1);
    }

    public function index()
    {
        $couriers = ShippingCourier::where('store_id', $this->storeId)
            ->orderBy('sort_order')
            ->get();

        return view('admin.settings.shipping-couriers.index', compact('couriers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['store_id'] = $this->storeId;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');

        ShippingCourier::create($validated);

        return redirect()->route('admin.shipping-couriers.index')
            ->with('success', 'Courier added.');
    }

    public function update(Request $request, ShippingCourier $shippingCourier)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');

        $shippingCourier->update($validated);

        return redirect()->route('admin.shipping-couriers.index')
            ->with('success', 'Courier updated.');
    }

    public function destroy(ShippingCourier $shippingCourier)
    {
        $hasOrders = \App\Models\Order::where('shipping_courier_id', $shippingCourier->id)->exists();
        if ($hasOrders) {
            return redirect()->route('admin.shipping-couriers.index')
                ->with('error', 'Cannot delete: courier is used by existing orders.');
        }

        $shippingCourier->delete();

        return redirect()->route('admin.shipping-couriers.index')
            ->with('success', 'Courier deleted.');
    }
}
