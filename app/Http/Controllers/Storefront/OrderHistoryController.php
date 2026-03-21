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
            ->latest()
            ->paginate(15);

        return view('account.orders', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_if($order->customer_id !== Auth::guard('customer')->id(), 403);

        $order->load(['products', 'histories', 'status']);

        return view('account.order-detail', compact('order'));
    }
}
