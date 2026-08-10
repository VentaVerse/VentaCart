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
            ->latest()
            ->paginate(15);

        return view('account.orders', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_if($order->customer_id !== Auth::guard('customer')->id(), 403);
        // Same sentinel as scopeVisibleToCustomer: never reached a status, as
        // opposed to had one whose config row was later deleted. Keying this on
        // order_status_id would 404 a customer out of their own paid order the
        // moment an admin removed a status.
        abort_if(is_null($order->order_status_name), 404);

        $order->load(['products', 'histories']);

        return view('account.order-detail', compact('order'));
    }
}
