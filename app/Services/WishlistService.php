<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The shopper's wishlist: stored for a signed-in customer, kept in the
 * session for a guest. Products are always read through the Product model,
 * so a product the shopper may not see never appears, even if it was saved
 * before it was hidden.
 */
class WishlistService
{
    public const SESSION_KEY = 'wishlist';

    /** How many products a wishlist holds at most. */
    public const LIMIT = 200;

    /** @return list<int> product ids, newest first */
    public function ids(): array
    {
        $customer = $this->customer();
        if ($customer) {
            return WishlistItem::where('customer_id', $customer->id)->latest('id')->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        }

        return array_values(array_map('intval', (array) session(self::SESSION_KEY, [])));
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function has(int $productId): bool
    {
        return in_array($productId, $this->ids(), true);
    }

    public function add(int $productId): void
    {
        $customer = $this->customer();
        if ($customer) {
            if (WishlistItem::where('customer_id', $customer->id)->count() < self::LIMIT) {
                WishlistItem::firstOrCreate(['customer_id' => $customer->id, 'product_id' => $productId]);
            }

            return;
        }

        $ids = $this->ids();
        if (! in_array($productId, $ids, true) && count($ids) < self::LIMIT) {
            array_unshift($ids, $productId);
            session([self::SESSION_KEY => $ids]);
        }
    }

    public function remove(int $productId): void
    {
        $customer = $this->customer();
        if ($customer) {
            WishlistItem::where('customer_id', $customer->id)->where('product_id', $productId)->delete();

            return;
        }

        session([self::SESSION_KEY => array_values(array_filter($this->ids(), fn ($id) => $id !== $productId))]);
    }

    /** The wishlist's products the shopper may see, in wishlist order. */
    public function products(): Collection
    {
        $ids = $this->ids();
        if ($ids === []) {
            return collect();
        }

        $products = Product::where('is_active', true)
            ->whereIn('id', $ids)
            ->with(['images', 'specials', \App\Support\ProductAttributes::CARD_RELATION])
            ->get()
            ->keyBy('id');

        return collect($ids)->map(fn ($id) => $products->get($id))->filter()->values();
    }

    /** Move a guest's session wishlist into the account they just signed in to. */
    public function mergeInto(Customer $customer): void
    {
        $ids = array_values(array_map('intval', (array) session(self::SESSION_KEY, [])));
        if ($ids !== []) {
            $existing = WishlistItem::where('customer_id', $customer->id)->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $room = max(0, self::LIMIT - count($existing));
            $valid = Product::whereIn('id', array_diff($ids, $existing))->pluck('id')->all();
            foreach (array_slice($valid, 0, $room) as $productId) {
                WishlistItem::firstOrCreate(['customer_id' => $customer->id, 'product_id' => (int) $productId]);
            }
        }
        session()->forget(self::SESSION_KEY);
    }

    private function customer(): ?Customer
    {
        $user = Auth::guard('customer')->user();

        return $user instanceof Customer ? $user : null;
    }
}
