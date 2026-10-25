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

        $blockOutOfStock = \App\Models\Setting::where('group', 'checkout')
            ->where('key', 'block_out_of_stock')
            ->value('value') ?? '1';

        $outOfStockIds = [];
        if ($blockOutOfStock === '1') {
            foreach ($items as $item) {
                $product = $item->product;
                if (!$product || !$product->track_stock) {
                    continue;
                }
                if ($item->productVariant) {
                    if ($item->productVariant->quantity < $item->quantity) {
                        $outOfStockIds[] = $item->id;
                    }
                } elseif ($product->quantity < $item->quantity) {
                    $outOfStockIds[] = $item->id;
                }
            }
        }

        // Lines that cannot be bought at all any more (product or variant
        // switched off since they were added). Blocked whatever the
        // out-of-stock setting says: there is nothing to sell.
        $unavailableIds = $items->filter(fn ($item) => $item->isUnavailable())->pluck('id')->values()->all();
        $outOfStockIds = array_values(array_diff($outOfStockIds, $unavailableIds));

        $hasOutOfStock = !empty($outOfStockIds);
        $hasUnavailable = !empty($unavailableIds);

        return view('cart.index', compact('items', 'subtotal', 'hasOutOfStock', 'outOfStockIds', 'hasUnavailable', 'unavailableIds'));
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

        $cart = $this->cartService->findCart();
        if (!$cart || $cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        $newQty = $request->integer('quantity');
        $blockOutOfStock = \App\Models\Setting::where('group', 'checkout')
            ->where('key', 'block_out_of_stock')
            ->value('value') ?? '1';

        // Check stock if blocking is enabled
        $stockWarning = null;
        $availableStock = null;
        if ($blockOutOfStock === '1' && $newQty > 0) {
            $cartItem->loadMissing(['product', 'productVariant']);
            $product = $cartItem->product;

            if ($product && $product->track_stock) {
                $availableStock = $cartItem->productVariant
                    ? $cartItem->productVariant->quantity
                    : $product->quantity;

                if ($newQty > $availableStock) {
                    $stockWarning = "Only {$availableStock} available in stock.";
                    $newQty = $availableStock > 0 ? $availableStock : 1;
                }
            }
        }

        $updated = $this->cartService->update($cartItem->id, $newQty);

        $response = [
            'success' => true,
            'count' => $this->cartService->getCount(),
            'subtotal' => $this->cartService->getSubtotal(),
            'quantity' => $newQty,
            // Recomputed per-unit price (tier may have changed) so the line refreshes.
            'price' => $updated ? (float) $updated->price : null,
        ];

        if ($stockWarning) {
            $response['stock_warning'] = $stockWarning;
            $response['available_stock'] = $availableStock;
        }

        return response()->json($response);
    }

    public function remove(CartItem $cartItem): JsonResponse
    {
        $cart = $this->cartService->findCart();
        if (!$cart || $cartItem->cart_id !== $cart->id) {
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
                    'image' => $item->image,
                ];
            })->values(),
            'subtotal' => $items->sum(fn($i) => $i->price * $i->quantity),
            'count' => $items->sum('quantity'),
        ]);
    }

    public function estimateShipping(Request $request)
    {
        $request->validate([
            'country_id' => 'required|integer|exists:countries,id',
            'state' => 'nullable|string',
            'city' => 'required|string',
        ]);

        $items = $this->cartService->getItems();
        $subtotal = $this->cartService->getSubtotal();

        $quoteService = app(\App\Services\Shipping\ShippingQuoteService::class);
        $quotes = $quoteService->getQuotes([
            'country_id' => $request->input('country_id'),
            'province' => $request->input('state', ''),
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
