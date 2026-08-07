<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaletteController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        foreach (Product::query()->where('name', 'like', "%{$q}%")->limit(5)->get() as $p) {
            $results[] = ['label' => $p->name, 'sub' => 'Product', 'url' => route('admin.products.edit', $p), 'group' => 'Products'];
        }

        foreach (
            Order::query()
                ->where('id', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->limit(5)
                ->get() as $o
        ) {
            $results[] = [
                'label' => "Order #{$o->id}",
                'sub' => trim("{$o->first_name} {$o->last_name}"),
                'url' => route('admin.orders.show', $o),
                'group' => 'Orders',
            ];
        }

        foreach (
            Customer::query()
                ->where('email', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->limit(5)
                ->get() as $c
        ) {
            $results[] = ['label' => trim("{$c->first_name} {$c->last_name}"), 'sub' => $c->email, 'url' => route('admin.customers.edit', $c), 'group' => 'Customers'];
        }

        return response()->json(['results' => $results]);
    }
}
