<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CartService
{
    private ?Cart $cart = null;
    private int $storeId;

    public function __construct()
    {
        $this->storeId = (int) config('app.store_id', 1);
    }

    public function getCart(): Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        $customer = Auth::guard('customer')->user();

        if ($customer) {
            $this->cart = Cart::firstOrCreate(
                ['customer_id' => $customer->id, 'store_id' => $this->storeId],
                ['session_id' => session()->getId()]
            );
        } else {
            $sessionId = session()->getId();
            $this->cart = Cart::firstOrCreate(
                ['session_id' => $sessionId, 'store_id' => $this->storeId, 'customer_id' => null],
            );
        }

        return $this->cart;
    }

    public function add(int $productId, int $quantity = 1, ?int $variantId = null): CartItem
    {
        $product = Product::where('id', $productId)
            ->where('is_active', true)
            ->firstOrFail();

        if ($product->has_variants && !$variantId) {
            throw ValidationException::withMessages([
                'variant_id' => 'Please select a variant.',
            ]);
        }

        $variant = null;
        $price = $product->price;
        $sku = $product->sku;
        $name = $product->name;
        $variantLabel = null;

        if ($variantId) {
            $variant = ProductVariant::where('id', $variantId)
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->firstOrFail();

            $variant->load('values');
            $price = $variant->price;
            $sku = $variant->sku ?: $product->sku;
            $variantLabel = $variant->name;
        }

        // Check stock availability
        $stockQty = $variant ? $variant->quantity : $product->quantity;
        if ($stockQty <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'This product is currently out of stock.',
            ]);
        }

        $cart = $this->getCart();
        $existingQtyQuery = $cart->items()->where('product_id', $productId);
        if ($variantId) {
            $existingQtyQuery->where('product_variant_id', $variantId);
        } else {
            $existingQtyQuery->whereNull('product_variant_id');
        }
        $existingQty = $existingQtyQuery->value('quantity') ?? 0;

        if (($existingQty + $quantity) > $stockQty) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$stockQty} available" . ($existingQty > 0 ? " (you already have {$existingQty} in your cart)" : '') . '.',
            ]);
        }

        // Find existing item (handle NULL variant_id explicitly)
        $existingQuery = $cart->items()
            ->where('product_id', $productId);

        if ($variantId) {
            $existingQuery->where('product_variant_id', $variantId);
        } else {
            $existingQuery->whereNull('product_variant_id');
        }

        $existing = $existingQuery->first();

        $totalQty = ($existing ? $existing->quantity : 0) + $quantity;

        // Resolve best price considering customer group and quantity
        $price = self::resolvePrice($product, $price, $totalQty);

        if ($existing) {
            $existing->update([
                'quantity' => $totalQty,
                'price' => $price,
                'name' => $name,
                'variant_label' => $variantLabel,
                'sku' => $sku,
            ]);
            return $existing->fresh();
        }

        return $cart->items()->create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'name' => $name,
            'variant_label' => $variantLabel,
            'price' => $price,
            'sku' => $sku,
        ]);
    }

    public function update(int $cartItemId, int $quantity): ?CartItem
    {
        $cart = $this->getCart();
        $item = $cart->items()->with('product.specials')->findOrFail($cartItemId);

        if ($quantity <= 0) {
            $this->remove($cartItemId);
            return null;
        }

        // Recalculate price based on new quantity
        $basePrice = $item->productVariant?->price ?? $item->product->price;
        $price = self::resolvePrice($item->product, $basePrice, $quantity);

        $item->update(['quantity' => $quantity, 'price' => $price]);
        return $item->fresh();
    }

    public function remove(int $cartItemId): void
    {
        $cart = $this->getCart();
        $cart->items()->where('id', $cartItemId)->delete();
    }

    public function clear(): void
    {
        $cart = $this->getCart();
        $cart->items()->delete();
    }

    public function getItems(): Collection
    {
        return $this->getCart()->items()
            ->with(['product.images', 'productVariant'])
            ->get();
    }

    public function getCount(): int
    {
        $customer = Auth::guard('customer')->user();

        if ($customer) {
            $cart = Cart::where('customer_id', $customer->id)
                ->where('store_id', $this->storeId)
                ->first();
        } else {
            $sessionId = session()->getId();
            $cart = Cart::where('session_id', $sessionId)
                ->where('store_id', $this->storeId)
                ->whereNull('customer_id')
                ->first();
        }

        if (!$cart) {
            return 0;
        }

        return (int) $cart->items()->sum('quantity');
    }

    public function getSubtotal(): float
    {
        $items = $this->getItems();
        return $items->sum(fn($item) => $item->price * $item->quantity);
    }

    public function migrateGuestCart(string $guestSessionId, Customer $customer): void
    {
        $guestCart = Cart::where('session_id', $guestSessionId)
            ->where('store_id', $this->storeId)
            ->whereNull('customer_id')
            ->first();

        if (!$guestCart || $guestCart->items()->count() === 0) {
            return;
        }

        $customerCart = Cart::firstOrCreate(
            ['customer_id' => $customer->id, 'store_id' => $this->storeId],
            ['session_id' => session()->getId()]
        );

        foreach ($guestCart->items as $guestItem) {
            $existingQuery = $customerCart->items()
                ->where('product_id', $guestItem->product_id);

            if ($guestItem->product_variant_id) {
                $existingQuery->where('product_variant_id', $guestItem->product_variant_id);
            } else {
                $existingQuery->whereNull('product_variant_id');
            }

            $existing = $existingQuery->first();

            if ($existing) {
                $existing->update([
                    'quantity' => $existing->quantity + $guestItem->quantity,
                ]);
            } else {
                $customerCart->items()->create($guestItem->only([
                    'product_id', 'product_variant_id', 'quantity',
                    'name', 'variant_label', 'price', 'sku',
                ]));
            }
        }

        $guestCart->items()->delete();
        $guestCart->delete();

        $this->cart = null;
    }

    /**
     * Resolve the best price for a product considering customer group, quantity, and date.
     *
     * Logic (mirrors OpenCart):
     * 1. Filter specials by date validity
     * 2. Filter by customer group (matching OR null = all groups)
     * 3. Filter by quantity threshold (quantity <= $qty)
     * 4. Sort by priority ASC (lower = higher precedence), then price ASC
     * 5. Return the lowest priced match, or the base price if none qualify
     */
    public static function resolvePrice(Product $product, float $basePrice, int $qty = 1): float
    {
        $customer = Auth::guard('customer')->user();
        $customerGroupId = $customer?->customer_group_id;
        $now = now();

        $bestPrice = $basePrice;

        $applicableSpecials = $product->specials
            ->filter(function ($s) use ($now, $customerGroupId, $qty) {
                // Date check
                if ($s->date_start && $s->date_start > $now) return false;
                if ($s->date_end && $s->date_end < $now) return false;

                // Customer group: null = all groups, otherwise must match
                if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;

                // Quantity threshold: price applies when buying >= this quantity
                if ($s->quantity > $qty) return false;

                return true;
            })
            ->sortBy([['priority', 'asc'], ['price', 'asc']]);

        $best = $applicableSpecials->first();
        if ($best && $best->price < $bestPrice) {
            $bestPrice = (float) $best->price;
        }

        return $bestPrice;
    }
}
