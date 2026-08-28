<?php

namespace Extensions\ErpSync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderStatus;
use App\Models\ShippingCourier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpCatalogController extends Controller
{
    use \App\Http\Controllers\Api\LogsApiCalls;

    public function categories(Request $request): JsonResponse
    {
        $query = Category::query()->select(['id', 'name', 'slug', 'parent_id', 'sort_order', 'is_active']);

        if ($request->filled('active_only')) {
            $query->where('is_active', true);
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->get()->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'parent_id' => $cat->parent_id,
                'sort_order' => $cat->sort_order,
                'is_active' => $cat->is_active,
            ];
        });

        return $this->logAndRespond($request, $categories);
    }

    public function brands(Request $request): JsonResponse
    {
        $query = Brand::query()->select(['id', 'name', 'slug', 'sort_order', 'is_active']);

        if ($request->filled('active_only')) {
            $query->where('is_active', true);
        }

        $brands = $query->orderBy('sort_order')->orderBy('name')->get()->map(function ($brand) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'sort_order' => $brand->sort_order,
                'is_active' => $brand->is_active,
            ];
        });

        return $this->logAndRespond($request, $brands);
    }

    public function orderStatuses(Request $request): JsonResponse
    {
        $statuses = OrderStatus::query()
            ->select(['id', 'name', 'is_default', 'deducts_stock', 'sort_order'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($status) {
                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'is_default' => $status->is_default,
                    'deducts_stock' => (bool) $status->deducts_stock,
                    // Deprecated alias kept so an ERP already reading
                    // stock_action does not break on this deploy. There is no
                    // 'increment' any more - returning stock is the automatic
                    // reversal of a deduction, never a status tag - so this
                    // field can now only be 'decrement' or null.
                    'stock_action' => $status->deducts_stock ? 'decrement' : null,
                    'sort_order' => $status->sort_order,
                    // Which fulfilment queue the status belongs to (unpaid,
                    // to_pack, to_handover, shipping, failed, lost_damaged,
                    // delivered, cancelled), or null when unplaced or when the
                    // courier-fulfillment extension is not installed. The ERP
                    // mirrors this store's own queue from it rather than
                    // guessing from status names.
                    'placement' => $this->placementFor((int) $status->id),
                ];
            });

        return $this->logAndRespond($request, $statuses);
    }

    /**
     * The store's couriers a parcel can be handed to by hand: the same list
     * the admin's "Record shipment" form offers. ?active_only=1 drops the
     * retired ones, which is what the ERP always asks for.
     */
    public function shippingCouriers(Request $request): JsonResponse
    {
        $query = ShippingCourier::query()->select(['id', 'name', 'tracking_url', 'is_active', 'sort_order']);

        if ($request->filled('active_only')) {
            $query->where('is_active', true);
        }

        $couriers = $query->orderBy('sort_order')->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'tracking_url' => $c->tracking_url,
            'is_active' => (bool) $c->is_active,
        ]);

        return $this->logAndRespond($request, ['data' => $couriers]);
    }

    /** The placement map lives in the courier-fulfillment extension; absent, every status is unplaced. */
    private function placementFor(int $orderStatusId): ?string
    {
        $map = 'Extensions\\CourierFulfillment\\PlacementMap';

        return class_exists($map) ? $map::placementFor($orderStatusId) : null;
    }
}
