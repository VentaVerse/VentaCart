<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Apply the stock consequence of an order arriving at $newStatus.
     *
     * The model (owner ruling, 2026-08-08) is deliberately two-valued: a
     * status either DEDUCTS stock or it does not. There is no "increment"
     * tag, because putting stock back is not an independent instruction - it
     * is the reversal of a deduction that already happened. So the decision
     * is a comparison between where the order's stock actually IS
     * (orders.stock_adjusted, the single source of truth for "these units are
     * currently out of inventory") and where the new status says it SHOULD be
     * (order_statuses.deducts_stock):
     *
     *   not deducted -> deducting status : take the units out, raise the flag
     *   deducted     -> non-deducting    : put the units back, clear the flag
     *   anything else                    : nothing to do
     *
     * Reading the flag rather than the OLD status is what makes the two edge
     * cases fall out for free: an order created directly at a deducting
     * status has no old status yet and still deducts exactly once, and
     * re-saving an order at the status it is already in can never
     * double-adjust.
     */
    public static function adjustForStatusChange(Order $order, OrderStatus $newStatus): void
    {
        $deducts = (bool) $newStatus->deducts_stock;

        // The row is re-read under a lock inside the transaction because
        // stock_adjusted is the guard against double-adjusting: two concurrent
        // status changes (an admin save racing a courier webhook, say) must
        // not both read "not adjusted" and both decrement.
        DB::transaction(function () use ($order, $deducts) {
            $fresh = Order::lockForUpdate()->find($order->id);
            if (! $fresh) {
                return;
            }

            if ($deducts && ! $fresh->stock_adjusted) {
                self::decrementStock($fresh);
                $fresh->update(['stock_adjusted' => true]);
            } elseif (! $deducts && $fresh->stock_adjusted) {
                self::incrementStock($fresh);
                $fresh->update(['stock_adjusted' => false]);
            }
        });
    }

    private static function decrementStock(Order $order): void
    {
        $order->loadMissing('products');

        foreach ($order->products as $item) {
            if ($item->product_id && $item->product?->track_stock) {
                $item->product->decrement('quantity', $item->quantity);
            }
            if ($item->product_variant_id && $item->productVariant) {
                $item->productVariant->decrement('quantity', $item->quantity);
            }
        }
    }

    private static function incrementStock(Order $order): void
    {
        $order->loadMissing('products');

        foreach ($order->products as $item) {
            if ($item->product_id && $item->product?->track_stock) {
                $item->product->increment('quantity', $item->quantity);
            }
            if ($item->product_variant_id && $item->productVariant) {
                $item->productVariant->increment('quantity', $item->quantity);
            }
        }
    }
}
