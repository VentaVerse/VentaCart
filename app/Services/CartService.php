<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductSpecial;
use App\Models\ProductVariant;
use App\Services\ActivityLogger;
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

    /**
     * Read-only lookup. Returns null if the visitor has no cart yet.
     * Use this on read paths (cart page, mini cart, checkout, coupon view) so
     * crawlers and curl-style requests don't manufacture empty cart rows.
     */
    public function findCart(): ?Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        $customer = Auth::guard('customer')->user();

        if ($customer) {
            $this->cart = Cart::where('customer_id', $customer->id)
                ->where('store_id', $this->storeId)
                ->first();
        } else {
            $this->cart = Cart::where('session_id', session()->getId())
                ->where('store_id', $this->storeId)
                ->whereNull('customer_id')
                ->first();
        }

        return $this->cart;
    }

    /**
     * Get-or-create. Use only on write paths (add to cart, apply coupon
     * to an existing cart, migrate guest cart on login).
     */
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
                ->first();

            // Switched off since the page was opened: say so, rather than
            // the bare "not found" a shopper cannot act on.
            if (! $variant || ! $variant->is_active) {
                throw ValidationException::withMessages([
                    'variant_id' => 'That option is no longer available.',
                ]);
            }

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

            ActivityLogger::log($cart->customer_id, 'cart_add', "Added {$name} to cart", $product, ['quantity' => $quantity, 'variant' => $variantLabel]);

            return $existing->fresh();
        }

        $item = $cart->items()->create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'name' => $name,
            'variant_label' => $variantLabel,
            'price' => $price,
            'sku' => $sku,
        ]);

        ActivityLogger::log($cart->customer_id, 'cart_add', "Added {$name} to cart", $product, ['quantity' => $quantity, 'variant' => $variantLabel]);

        return $item;
    }

    public function update(int $cartItemId, int $quantity): ?CartItem
    {
        $cart = $this->findCart();
        if (!$cart) {
            return null;
        }

        $item = $cart->items()->with('product.specials')->findOrFail($cartItemId);

        if ($quantity <= 0) {
            $this->remove($cartItemId);
            return null;
        }

        $basePrice = $item->productVariant?->price ?? $item->product->price;
        $price = self::resolvePrice($item->product, $basePrice, $quantity);

        $item->update(['quantity' => $quantity, 'price' => $price]);
        return $item->fresh();
    }

    public function remove(int $cartItemId): void
    {
        $cart = $this->findCart();
        if (!$cart) {
            return;
        }
        $cart->items()->where('id', $cartItemId)->delete();
    }

    public function clear(): void
    {
        $cart = $this->findCart();
        if (!$cart) {
            return;
        }
        $cart->items()->delete();
    }

    public function getItems(): Collection
    {
        $cart = $this->findCart();
        if (!$cart) {
            return new Collection();
        }

        $items = $cart->items()
            ->with(['product.images', 'product.specials', 'productVariant'])
            ->get();

        $this->refreshPrices($items);

        return $items;
    }

    /**
     * Recalculate prices for all cart items based on current pricing rules.
     * Catches: guest→login group pricing, expired/new specials, admin price changes, group changes.
     */
    protected function refreshPrices(Collection $items): void
    {
        $customerGroupId = Auth::guard('customer')->user()?->customer_group_id;

        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }

            // Start with base product or variant price
            $basePrice = $item->productVariant
                ? (float) $item->productVariant->price
                : (float) $product->price;

            $correctPrice = self::resolvePrice($product, $basePrice, $item->quantity, $customerGroupId);

            if ((float) $item->price !== $correctPrice) {
                $item->update(['price' => $correctPrice]);
                $item->price = $correctPrice;
            }
        }
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
     * Resolve the best price for a product/variant considering specials.
     *
     * Specials are stored against product.price (the base). When the cart item
     * is a variant, $basePrice is variant.price (= product.price + modifier).
     * The special's price is treated as a *new base*, and the variant's modifier
     * carries over on top — so a sale of 720→500 on product P with variants at
     * +0/+280/+560 yields 500/780/1060, not a flat 500 across all variants.
     *
     * Math:
     *   modifier = basePrice − product.price   (0 for non-variant items)
     *   effective_variant_price = special.price + modifier
     *   final = max(0, effective_variant_price) when a special applies, else basePrice
     */
    public static function resolvePrice(Product $product, float $basePrice, int $qty = 1, ?int $customerGroupId = null): float
    {
        $special = self::winningSpecial($product, $qty, $customerGroupId);

        if ($special === null) {
            return max(0.0, $basePrice);
        }

        $modifier = $basePrice - (float) $product->price;

        return max(0.0, (float) $special->price + $modifier);
    }

    /**
     * The customer group prices are worked out for: the signed-in
     * customer's, else the store's default group, so guests get the
     * default group's specials and tiers just as the cart charges them.
     */
    public static function customerGroupId(): ?int
    {
        return Auth::guard('customer')->user()?->customer_group_id
            ?? once(fn () => \App\Models\CustomerGroup::defaultId());
    }

    /**
     * The special the cart applies to $qty units, or null when none does.
     *
     * Quantity-tier semantics: the DEEPEST tier whose threshold is at or
     * below the quantity wins (the most specific tier for this quantity),
     * whether its price is above or below the base. Within a tier, the lower
     * priority value wins, then the lower price.
     */
    public static function winningSpecial(Product $product, int $qty = 1, ?int $customerGroupId = null): ?ProductSpecial
    {
        $customerGroupId ??= self::customerGroupId();
        $now = now();

        $applicable = $product->specials
            ->filter(function ($s) use ($now, $customerGroupId, $qty) {
                if (! $s->isActiveAt($now)) return false;
                if ($s->customer_group_id !== null && $s->customer_group_id != $customerGroupId) return false;
                if ($s->quantity > $qty) return false;
                return true;
            });

        if ($applicable->isEmpty()) {
            return null;
        }

        return $applicable
            ->where('quantity', $applicable->max('quantity'))
            ->sortBy([['priority', 'asc'], ['price', 'asc']])
            ->first();
    }
}
