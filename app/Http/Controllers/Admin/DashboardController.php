<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Review;
use App\Models\StockNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $storeId = (int) config('app.store_id', 1);
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // === KPI Cards ===
        $revenueThisMonth = Order::where('store_id', $storeId)
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->whereNotIn('order_status_id', $this->cancelledStatusIds($storeId))
            ->sum('total');

        $revenueLastMonth = Order::where('store_id', $storeId)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotIn('order_status_id', $this->cancelledStatusIds($storeId))
            ->sum('total');

        $ordersThisMonth = Order::where('store_id', $storeId)
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->count();

        $ordersLastMonth = Order::where('store_id', $storeId)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $customersTotal = Customer::where('store_id', $storeId)->count();
        $customersThisMonth = Customer::where('store_id', $storeId)
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->count();

        $avgOrderValue = Order::where('store_id', $storeId)
            ->whereNotIn('order_status_id', $this->cancelledStatusIds($storeId))
            ->where('total', '>', 0)
            ->avg('total') ?: 0;

        // === Revenue Chart (last 30 days, daily) ===
        $revenueDaily = Order::where('store_id', $storeId)
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->whereNotIn('order_status_id', $this->cancelledStatusIds($storeId))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as revenue'), DB::raw('COUNT(*) as order_count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $revenueDates = [];
        $revenueValues = [];
        $orderCounts = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->format('Y-m-d');
            $revenueDates[] = Carbon::parse($date)->format('M d');
            $revenueValues[] = (float) ($revenueDaily[$date]->revenue ?? 0);
            $orderCounts[] = (int) ($revenueDaily[$date]->order_count ?? 0);
        }

        // === Order Status Breakdown ===
        $statusBreakdown = Order::where('orders.store_id', $storeId)
            ->join('order_statuses', 'orders.order_status_id', '=', 'order_statuses.id')
            ->select('order_statuses.name', DB::raw('COUNT(*) as count'))
            ->groupBy('order_statuses.name')
            ->orderByDesc('count')
            ->get();

        // === Payment Method Breakdown ===
        $paymentBreakdown = Order::where('store_id', $storeId)
            ->whereNotNull('payment_method')
            ->select(DB::raw("CASE
                WHEN payment_method LIKE 'bank_transfer%' THEN 'Bank Transfer'
                WHEN payment_method = 'hitpay' THEN 'HitPay'
                WHEN payment_method = 'cod' THEN 'COD'
                ELSE 'Other'
            END as method"), DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy('method')
            ->get();

        // === Inventory Health ===
        $inventoryHealth = [
            'in_stock' => Product::where('is_active', true)->where('quantity', '>', 5)->count(),
            'low_stock' => Product::where('is_active', true)->where('quantity', '>', 0)->where('quantity', '<=', 5)->count(),
            'out_of_stock' => Product::where('is_active', true)->where('quantity', '<=', 0)->count(),
            'total_active' => Product::where('is_active', true)->count(),
        ];

        // === Top Selling Products (by order quantity) ===
        $p = config('database.connections.mysql.prefix', '');
        $topProducts = DB::table('order_products')
            ->join('orders', 'order_products.order_id', '=', 'orders.id')
            ->where('orders.store_id', $storeId)
            ->whereNotIn('orders.order_status_id', $this->cancelledStatusIds($storeId))
            ->select('order_products.name', DB::raw("SUM({$p}order_products.quantity) as qty_sold"), DB::raw("SUM({$p}order_products.price * {$p}order_products.quantity) as revenue"))
            ->groupBy('order_products.name')
            ->orderByDesc('qty_sold')
            ->limit(8)
            ->get();

        // === Pending Actions ===
        $pendingOrders = Order::where('store_id', $storeId)
            ->whereIn('order_status_id', $this->pendingStatusIds($storeId))
            ->count();

        $pendingReviews = Review::pending()->count();

        $waitingNotifications = StockNotification::waiting()->count();

        $lowStockProducts = Product::where('is_active', true)
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 5)
            ->with('brand')
            ->orderBy('quantity')
            ->limit(5)
            ->get();

        // === Recent Orders ===
        $recentOrders = Order::where('store_id', $storeId)
            ->with(['status', 'customer'])
            ->orderByDesc('created_at')
            ->limit(7)
            ->get();

        return view('admin.dashboard', compact(
            'revenueThisMonth', 'revenueLastMonth',
            'ordersThisMonth', 'ordersLastMonth',
            'customersTotal', 'customersThisMonth',
            'avgOrderValue',
            'revenueDates', 'revenueValues', 'orderCounts',
            'statusBreakdown', 'paymentBreakdown',
            'inventoryHealth', 'topProducts',
            'pendingOrders', 'pendingReviews', 'waitingNotifications',
            'lowStockProducts', 'recentOrders'
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
}
