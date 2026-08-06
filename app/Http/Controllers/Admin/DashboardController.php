<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Cart;
use App\Models\CustomerActivity;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Review;
use App\Models\SeoSuggestion;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $storeId = (int) config('app.store_id', 1);
        $now = Carbon::now();
        $startOfToday = $now->copy()->startOfDay();

        // === Stat row ===
        $revenueToday = Order::where('store_id', $storeId)
            ->where('created_at', '>=', $startOfToday)
            ->whereNotIn('order_status_id', $this->cancelledStatusIds($storeId))
            ->sum('total');

        $ordersToday = Order::where('store_id', $storeId)
            ->where('created_at', '>=', $startOfToday)
            ->count();

        $pendingOrders = Order::where('store_id', $storeId)
            ->whereIn('order_status_id', $this->pendingStatusIds($storeId))
            ->count();

        $lowStockCount = Product::where('is_active', true)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 5)
            ->count();

        // === Recent orders ===
        $recentOrders = Order::where('store_id', $storeId)
            ->with(['status', 'customer'])
            ->orderByDesc('created_at')
            ->limit(7)
            ->get();

        // === Needs attention ===
        $pendingReviews = Review::pending()->count();
        $pendingSeoSuggestions = SeoSuggestion::pending()->count();
        $recoverableCarts = Cart::whereHas('items')
            ->where('updated_at', '<', $now->copy()->subHour())
            ->count();

        // === Automation health ===
        $automations = Automation::enabled()->orderBy('name')->get();
        $unhealthyAutomations = $automations->filter(
            fn (Automation $automation) => $this->isAutomationUnhealthy($automation)
        )->values();

        // === Customer activity ===
        $recentActivities = CustomerActivity::with('customer')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('admin.dashboard', compact(
            'revenueToday', 'ordersToday', 'pendingOrders', 'lowStockCount',
            'recentOrders',
            'pendingReviews', 'pendingSeoSuggestions', 'recoverableCarts',
            'automations', 'unhealthyAutomations',
            'recentActivities'
        ));
    }

    private function cancelledStatusIds(int $storeId): array
    {
        return OrderStatus::where('store_id', $storeId)
            ->whereIn('name', ['Canceled', 'Cancelled', 'Refunded', 'Expired'])
            ->pluck('id')
            ->all();
    }

    private function pendingStatusIds(int $storeId): array
    {
        return OrderStatus::where('store_id', $storeId)
            ->whereIn('name', ['Pending', 'Unpaid'])
            ->pluck('id')
            ->all();
    }

    /**
     * An enabled automation is unhealthy if its last run failed, or if it
     * missed the run the cron expression says should already have happened
     * (never run, or last run predates that expected run time).
     */
    private function isAutomationUnhealthy(Automation $automation): bool
    {
        if ($automation->last_run_status === 'failed') {
            return true;
        }

        if (! $automation->cron_expression) {
            return false;
        }

        try {
            $expectedRun = (new CronExpression($automation->cron_expression))->getPreviousRunDate(now());
        } catch (Throwable $e) {
            return false;
        }

        return ! $automation->last_run_at || $automation->last_run_at->lt($expectedRun);
    }
}
