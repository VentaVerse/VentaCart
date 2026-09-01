<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatus;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\NotifyMap;
use Extensions\CourierFulfillment\PlacementMap;
use Extensions\CourierFulfillment\PlacementSuggester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Status placement: which fulfillment queue tab each core order status belongs
 * to, edited in one place inside the module's own settings (owner ruling,
 * 2026-08-09 - "Fulfilment is its own module, mapping should be inside the
 * fulfilment setting").
 *
 * This screen replaces the placement select that used to sit on core's Order
 * Statuses page. That page is plain CRUD again; the mapping is module data,
 * stored in the module's own table (PlacementMap) and removed with the module.
 *
 * Statuses with NO placement are listed FIRST, because that is the state of a
 * fresh install (the module seeds nothing) and it is the work this page
 * exists to finish. They are not hidden orders: an unplaced status's orders
 * appear in the queue's All tab, which is what the "Not placed (All tab only)"
 * option is named for.
 */
class StatusPlacementController extends Controller
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
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);

        $placements = PlacementMap::all();

        // Live order count per status: one grouped query, never N+1, scoped to
        // the same store the list is. The merchant sees the scale of what
        // re-placing a status moves before moving it.
        $orderCounts = Order::where('store_id', $this->storeId)
            ->whereNotNull('order_status_id')
            ->select('order_status_id', DB::raw('COUNT(*) as n'))
            ->groupBy('order_status_id')
            ->pluck('n', 'order_status_id');

        $rows = $statuses->map(fn (OrderStatus $status) => [
            'id'         => $status->id,
            'name'       => $status->name,
            'placement'  => $placements[$status->id] ?? null,
            'notifies'   => NotifyMap::notifies($status->id),
            'orders'     => (int) ($orderCounts[$status->id] ?? 0),
            // Offered by the "Suggest placements" button only, never applied
            // on its own (the module places nothing without being asked).
            'suggestion' => PlacementSuggester::suggest($status->name),
        ]);

        return view('courier-fulfillment::settings-placement', [
            'unplaced'       => $rows->whereNull('placement')->values(),
            'placed'         => $rows->whereNotNull('placement')->values(),
            'placementNames' => FulfillmentService::TAB_LABELS,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'placements'   => ['array'],
            'placements.*' => ['nullable', Rule::in(FulfillmentService::PLACEMENTS)],
            'notify'       => ['array'],
            'notify.*'     => ['nullable'],
        ]);

        // Only this store's own statuses can be placed: the form's keys are
        // ids, and an id from another store (or a deleted one) must not be
        // able to write a mapping row through this endpoint.
        $ownIds = OrderStatus::where('store_id', $this->storeId)->pluck('id')->all();

        foreach ($validated['placements'] ?? [] as $statusId => $placement) {
            $statusId = (int) $statusId;
            if (!in_array($statusId, $ownIds, true)) {
                continue;
            }

            // Null/empty is "not placed": the row is removed rather than
            // stored as a special value, so unplaced stays the absence of a
            // mapping everywhere in the module.
            PlacementMap::put($statusId, $placement ?: null);
        }

        // Whose statuses tell the customer. Read from the same submission as
        // the placements, and scoped to this store's own ids for the same
        // reason: the form's keys are ids and an id from elsewhere must not
        // be able to write policy through this endpoint.
        NotifyMap::put(array_values(array_filter(
            array_map('intval', array_keys($validated['notify'] ?? [])),
            fn ($id) => in_array($id, $ownIds, true)
        )));

        return redirect()->route('admin.fulfillment.settings.placement')
            ->with('success', 'Status Mapping saved.');
    }
}
