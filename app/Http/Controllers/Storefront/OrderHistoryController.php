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
            ->latest()
            ->paginate(15);

        return view('account.orders', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_if($order->customer_id !== Auth::guard('customer')->id(), 403);
        abort_if(is_null($order->order_status_id), 404);

        $order->load(['products', 'histories.status', 'status', 'shippingCourier']);

        return view('account.order-detail', compact('order'));
    }
}
