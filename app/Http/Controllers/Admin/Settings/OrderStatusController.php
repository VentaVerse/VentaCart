<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderStatusController extends Controller
{
    /**
     * The eight fulfillment_placement values, one per fulfillment queue tab,
     * in tab order. The queue's All tab is deliberately absent: it is a VIEW
     * over every order, never a placement a status can be put into.
     */
    private const PLACEMENTS = [
        'unpaid', 'to_pack', 'to_handover', 'shipping',
        'failed', 'lost_damaged', 'delivered', 'cancelled',
    ];

    private int $storeId;

    public function __construct()
    {
        $this->storeId = (int) config('app.store_id', 1);
    }

    /**
     * Route-model binding resolves an order status by id alone, so a status
     * belonging to another store would otherwise be editable and deletable
     * through this page's own endpoints even though index() never lists it.
     * 404 (not 403) keeps the answer identical to a status that does not
     * exist, which is what a caller outside this store is entitled to know.
     */
    private function authorizeStore(OrderStatus $orderStatus): void
    {
        abort_if((int) $orderStatus->store_id !== $this->storeId, 404);
    }

    public function index()
    {
        $statuses = OrderStatus::where('store_id', $this->storeId)
            ->orderBy('sort_order')
            ->get();

        // Live order count per status, one grouped query (never N+1): lets
        // the owner see the scale of what a placement regrouping moves.
        // Scoped to the same store the list above is scoped to, so the count
        // beside a status can only ever describe orders that status governs.
        $orderCounts = Order::where('store_id', $this->storeId)
            ->whereNotNull('order_status_id')
            ->select('order_status_id', DB::raw('COUNT(*) as n'))
            ->groupBy('order_status_id')
            ->pluck('n', 'order_status_id');

        return view('admin.settings.order-statuses.index', compact('statuses', 'orderCounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stock_action' => ['nullable', 'in:decrement,increment'],
            'fulfillment_placement' => ['nullable', 'in:' . implode(',', self::PLACEMENTS)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['boolean'],
        ]);

        $validated['store_id'] = $this->storeId;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['fulfillment_placement'] = $validated['fulfillment_placement'] ?? 'to_pack';
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
        $this->authorizeStore($orderStatus);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stock_action' => ['nullable', 'in:decrement,increment'],
            'fulfillment_placement' => ['nullable', 'in:' . implode(',', self::PLACEMENTS)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['fulfillment_placement'] = $validated['fulfillment_placement'] ?? 'to_pack';
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
        $this->authorizeStore($orderStatus);

        if ($orderStatus->is_default) {
            return redirect()->route('admin.order-statuses.index')
                ->with('error', 'Cannot delete the default status.');
        }

        $hasOrders = \App\Models\Order::where('order_status_id', $orderStatus->id)->exists();
        if ($hasOrders) {
            return redirect()->route('admin.order-statuses.index')
                ->with('error', 'Cannot delete: status is used by existing orders.');
        }

        $orderStatus->delete();

        return redirect()->route('admin.order-statuses.index')
            ->with('success', 'Order status deleted.');
    }
}
