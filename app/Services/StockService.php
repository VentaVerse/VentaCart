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

    /**
     * Keep stock honest when an order's LINES change rather than its status.
     *
     * stock_adjusted says "this order's current lines are out of inventory".
     * Rewriting the lines while it is up silently makes that a lie: the flag
     * then describes a list that no longer exists, and the eventual return
     * walks the new list, handing back units never taken and never returning
     * the ones still out. Swapping 2 of X for 3 of Y and cancelling used to
     * leave X permanently down 2 and Y permanently up 3.
     *
     * So an edit reconciles: put the old lines back, take the new lines out.
     * When nothing is out yet the flag is down and this does nothing at all,
     * because there is no deduction to correct.
     *
     * Call it AFTER the new lines are saved, with the lines as they were
     * before. When a save changes lines AND status, call this first: it
     * settles the stock against the new lines under the old flag, leaving
     * adjustForStatusChange() to do exactly what it always does.
     *
     * @param  \Illuminate\Support\Collection|iterable  $oldLines  the order's lines before the edit
     */
    public static function reconcileLines(Order $order, iterable $oldLines): void
    {
        DB::transaction(function () use ($order, $oldLines) {
            $fresh = Order::lockForUpdate()->find($order->id);

            // Nothing is out, so there is nothing to correct. The status
            // change that eventually deducts will read the new lines.
            if (! $fresh || ! $fresh->stock_adjusted) {
                return;
            }

            self::moveLines($oldLines, +1);

            $fresh->load('products');
            self::moveLines($fresh->products, -1);
        });
    }

    /**
     * Move every line by $sign units.
     *
     * A variant line moves the variant, then recomputes its parent from the
     * switched-on variants (Product::syncStockFromVariants). Recomputing
     * rather than moving the parent by the same units keeps it right when
     * the variant is switched off: its units are not the parent's to count.
     *
     * @param  \Illuminate\Support\Collection|iterable  $lines
     */
    private static function moveLines(iterable $lines, int $sign): void
    {
        foreach ($lines as $item) {
            $product = $item->product;

            // track_stock off means the merchant does not want Venta managing
            // this product's inventory. That has to cover its variants too:
            // moving a variant while its parent stays put breaks the
            // parent-equals-sum rule just as surely as the reverse.
            if (! $product || ! $product->track_stock) {
                continue;
            }

            $units = $sign * (int) $item->quantity;

            if ($item->product_variant_id && $item->productVariant) {
                $item->productVariant->increment('quantity', $units);

                // A line can outlive its product's variants (the flag was
                // turned off since): then the product's own stock moves, as
                // a product without variants does.
                if ($product->has_variants) {
                    $product->syncStockFromVariants();
                } else {
                    $product->increment('quantity', $units);
                }

                continue;
            }

            if ($item->product_id) {
                $product->increment('quantity', $units);
            }
        }
    }

    private static function decrementStock(Order $order): void
    {
        $order->loadMissing('products');
        self::moveLines($order->products, -1);
    }

    private static function incrementStock(Order $order): void
    {
        $order->loadMissing('products');
        self::moveLines($order->products, +1);
    }
}
