<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpCatalogController extends Controller
{
    use LogsApiCalls;

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
                ];
            });

        return $this->logAndRespond($request, $statuses);
    }
}
