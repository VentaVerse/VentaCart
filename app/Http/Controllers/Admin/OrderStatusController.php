<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
class OrderStatusController extends Controller
{
    private int $storeId;

    public function __construct()
    {
        $this->storeId = (int) config('app.store_id', 1);
    }

    public function index()
    {
        $statuses = OrderStatus::where('store_id', $this->storeId)
            ->orderBy('sort_order')
            ->get();

        return view('admin.order-statuses.index', compact('statuses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stock_action' => ['nullable', 'in:decrement,increment'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['boolean'],
        ]);

        $validated['store_id'] = $this->storeId;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            OrderStatus::where('store_id', $this->storeId)->update(['is_default' => false]);
        }

        OrderStatus::create($validated);

        return redirect()->route('admin.order-statuses.index')
            ->with('success', 'Order status created.');
    }

    public function update(Request $request, OrderStatus $orderStatus)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stock_action' => ['nullable', 'in:decrement,increment'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            OrderStatus::where('store_id', $this->storeId)->where('id', '!=', $orderStatus->id)->update(['is_default' => false]);
        }

        $orderStatus->update($validated);

        return redirect()->route('admin.order-statuses.index')
            ->with('success', 'Order status updated.');
    }

    public function destroy(OrderStatus $orderStatus)
    {
        if ($orderStatus->is_default) {
            return redirect()->route('admin.order-statuses.index')
                ->with('error', 'Cannot delete the default status.');
        }

        $hasOrders = \App\Models\Order::where('order_status_id', $orderStatus->id)->exists();
        if ($hasOrders) {
            return redirect()->route('admin.order-statuses.index')
                ->with('error', 'Cannot delete — status is used by existing orders.');
        }

        $orderStatus->delete();

        return redirect()->route('admin.order-statuses.index')
            ->with('success', 'Order status deleted.');
    }
}
