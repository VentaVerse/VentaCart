<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;

class AbandonedCartController extends Controller
{
    public function index(Request $request)
    {
        $query = Cart::with(['customer', 'items.product'])
            ->whereHas('items')
            ->where('updated_at', '<', now()->subHour());

        if ($search = $request->input('search')) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->input('type') === 'guest') {
            $query->whereNull('customer_id');
        } elseif ($request->input('type') === 'registered') {
            $query->whereNotNull('customer_id');
        }

        $carts = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();

        $totalAbandoned = Cart::whereHas('items')->where('updated_at', '<', now()->subHour())->count();
        $totalValue = Cart::whereHas('items')
            ->where('updated_at', '<', now()->subHour())
            ->with('items')
            ->get()
            ->sum(fn ($cart) => $cart->total);

        return view('admin.abandoned-carts.index', compact('carts', 'totalAbandoned', 'totalValue'));
    }

    public function show(Cart $cart)
    {
        $cart->load(['customer', 'items.product']);

        return view('admin.abandoned-carts.show', compact('cart'));
    }

    public function destroy(Cart $cart)
    {
        $cart->items()->delete();
        $cart->delete();

        return redirect()->route('admin.abandoned-carts.index')->with('success', 'Abandoned cart deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('error', 'No carts selected.');
        }

        Cart::whereIn('id', $ids)->each(function ($cart) {
            $cart->items()->delete();
            $cart->delete();
        });

        return back()->with('success', count($ids) . ' abandoned cart(s) deleted.');
    }
}
