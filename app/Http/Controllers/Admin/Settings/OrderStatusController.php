<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use Illuminate\Http\Request;

/**
 * Plain core CRUD for order statuses.
 *
 * It carries NO fulfillment concepts (owner ruling, 2026-08-09): the
 * status-to-queue-tab mapping the courier-fulfillment module needs is module
 * data, edited on that module's own Status placement page and stored in its
 * own table. Core has no fulfillment column and no placement field here, so an
 * install without the module is identical to one with it.
 */
class OrderStatusController extends Controller
{
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

        return view('admin.settings.order-statuses.index', compact('statuses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'deducts_stock' => ['boolean'],
            'counts_as_revenue' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['boolean'],
            // Fulfillment placement left core with the column it lived on
            // (owner ruling, 2026-08-09). Refused outright rather than
            // silently dropped, so a stale form or a hand-rolled request
            // gets told the field moved instead of appearing to save.
            'fulfillment_placement' => ['prohibited'],
        ]);

        $validated['store_id'] = $this->storeId;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_default'] = $request->boolean('is_default');
        // Coerced only AFTER validation, so a bogus value is rejected rather
        // than quietly read as false.
        $validated['deducts_stock'] = $request->boolean('deducts_stock');
        $validated['counts_as_revenue'] = $request->boolean('counts_as_revenue');

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
            'deducts_stock' => ['boolean'],
            'counts_as_revenue' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['boolean'],
            // Fulfillment placement left core with the column it lived on
            // (owner ruling, 2026-08-09). Refused outright rather than
            // silently dropped, so a stale form or a hand-rolled request
            // gets told the field moved instead of appearing to save.
            'fulfillment_placement' => ['prohibited'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_default'] = $request->boolean('is_default');
        // Coerced only AFTER validation, so a bogus value is rejected rather
        // than quietly read as false.
        $validated['deducts_stock'] = $request->boolean('deducts_stock');
        $validated['counts_as_revenue'] = $request->boolean('counts_as_revenue');

        if ($validated['is_default']) {
            OrderStatus::where('store_id', $this->storeId)->where('id', '!=', $orderStatus->id)->update(['is_default' => false]);
        }

        $orderStatus->update($validated);

        return redirect()->route('admin.order-statuses.index')
            ->with('success', 'Order status updated.');
    }

    /**
     * The whole table is one save scope (the doctrine): every row's
     * fields ride one submit, and the default is a radio, so exactly
     * one status holds it by construction.
     */
    public function updateAll(Request $request)
    {
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.deducts_stock' => ['boolean'],
            'rows.*.counts_as_revenue' => ['boolean'],
            'rows.*.customer_can_cancel' => ['boolean'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'default_id' => ['required', 'integer'],
        ]);

        $statuses = OrderStatus::where('store_id', $this->storeId)
            ->whereIn('id', array_keys($validated['rows']))
            ->get()
            ->keyBy('id');

        abort_unless($statuses->has((int) $validated['default_id']), 422);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $statuses) {
            foreach ($validated['rows'] as $id => $row) {
                $status = $statuses->get((int) $id);
                if (! $status) {
                    continue;
                }
                $status->update([
                    'name' => $row['name'],
                    'deducts_stock' => filter_var($row['deducts_stock'] ?? false, FILTER_VALIDATE_BOOL),
                    'counts_as_revenue' => filter_var($row['counts_as_revenue'] ?? false, FILTER_VALIDATE_BOOL),
                    'customer_can_cancel' => filter_var($row['customer_can_cancel'] ?? false, FILTER_VALIDATE_BOOL),
                    'sort_order' => $row['sort_order'] ?? 0,
                    'is_default' => (int) $id === (int) $validated['default_id'],
                ]);
            }
        });

        return redirect()->route('admin.order-statuses.index')
            ->with('success', 'Statuses saved.');
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
