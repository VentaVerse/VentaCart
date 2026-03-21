<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService
    ) {}

    public function index()
    {
        $items = $this->cartService->getItems();
        $subtotal = $items->sum(fn($item) => $item->price * $item->quantity);

        return view('cart.index', compact('items', 'subtotal'));
    }

    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'integer|min:1',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $item = $this->cartService->add(
            $request->integer('product_id'),
            $request->integer('quantity', 1),
            $request->integer('variant_id') ?: null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Added to cart',
            'count' => $this->cartService->getCount(),
            'subtotal' => $this->cartService->getSubtotal(),
        ]);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $cart = $this->cartService->getCart();
        if ($cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        $this->cartService->update($cartItem->id, $request->integer('quantity'));

        return response()->json([
            'success' => true,
            'count' => $this->cartService->getCount(),
            'subtotal' => $this->cartService->getSubtotal(),
        ]);
    }

    public function remove(CartItem $cartItem): JsonResponse
    {
        $cart = $this->cartService->getCart();
        if ($cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        $this->cartService->remove($cartItem->id);

        return response()->json([
            'success' => true,
            'count' => $this->cartService->getCount(),
            'subtotal' => $this->cartService->getSubtotal(),
        ]);
    }

    public function mini(): JsonResponse
    {
        $items = $this->cartService->getItems();

        return response()->json([
            'items' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'variant_label' => $item->variant_label,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'line_total' => $item->price * $item->quantity,
                    'image' => $item->product?->images?->first()?->path,
                ];
            })->values(),
            'subtotal' => $items->sum(fn($i) => $i->price * $i->quantity),
            'count' => $items->sum('quantity'),
        ]);
    }

    public function estimateShipping(Request $request)
    {
        $request->validate([
            'province' => 'required|string',
            'city' => 'required|string',
        ]);

        $items = $this->cartService->getItems();
        $subtotal = $this->cartService->getSubtotal();

        $quoteService = app(\App\Services\Shipping\ShippingQuoteService::class);
        $quotes = $quoteService->getQuotes([
            'province' => $request->input('province'),
            'city' => $request->input('city'),
            'barangay' => $request->input('barangay', ''),
        ], $items, $subtotal);

        return response()->json(['methods' => $quotes]);
    }

    public function clear()
    {
        $this->cartService->clear();

        return redirect()->route('cart.index');
    }
}
