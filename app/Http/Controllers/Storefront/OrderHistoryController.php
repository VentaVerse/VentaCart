<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class OrderHistoryController extends Controller
{
    public function index()
    {
        $orders = Auth::guard('customer')->user()
            ->orders()
            ->visibleToCustomer()
            // The list prints a line count per row; without this it ran one
            // COUNT query per order.
            ->withCount('products')
            ->with('status')
            ->latest()
            ->paginate(15);

        $cancellable = \App\Services\CustomerOrderCancellation::allowedMany($orders, Auth::guard('customer')->user());

        return view('account.orders', compact('orders', 'cancellable'));
    }

    public function show(Order $order)
    {
        abort_if($order->customer_id !== Auth::guard('customer')->id(), 403);
        // Same sentinel as scopeVisibleToCustomer: never reached a status, as
        // opposed to had one whose config row was later deleted. Keying this on
        // order_status_id would 404 a customer out of their own paid order the
        // moment an admin removed a status.
        abort_if(is_null($order->order_status_name), 404);

        $order->load(['products', 'histories', 'status']);
        $canCancel = \App\Services\CustomerOrderCancellation::allowed($order, Auth::guard('customer')->user());

        return view('account.order-detail', compact('order', 'canCancel'));
    }

    /**
     * The customer cancels their own order, while its status still allows
     * it. The service holds the rule and the row lock; this only answers
     * the page.
     */
    public function cancel(\Illuminate\Http\Request $request, Order $order)
    {
        abort_if($order->customer_id !== Auth::guard('customer')->id(), 403);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $status = \App\Services\CustomerOrderCancellation::cancel($order, Auth::guard('customer')->user(), $validated['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return redirect()->route('account.orders.show', $order)->with('error', $e->getMessage());
        }

        return redirect()->route('account.orders.show', $order)->with('success', 'Order #' . $order->id . ' cancelled. It is now ' . $status->name . '.');
    }
}
