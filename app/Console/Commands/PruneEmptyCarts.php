<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Safety net for the cart leak fix in CartService::findCart().
 * Even after all read paths stop creating empty rows, this guarantees
 * any future code path that slips through gets cleaned up daily.
 *
 * Only deletes guest carts (customer_id IS NULL) that have:
 *  - no items linked, AND
 *  - not been touched in over 24h.
 *
 * Logged-in customers' carts are preserved — abandoned-cart notifications
 * depend on them.
 */
class PruneEmptyCarts extends Command
{
    protected $signature = 'cart:prune-empty';

    protected $description = 'Delete guest carts with no items that have been untouched for over 24h';

    public function handle(): int
    {
        $cutoff = now()->subDay();

        $deleted = DB::table('carts')
            ->whereNull('customer_id')
            ->where('updated_at', '<', $cutoff)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('cart_items')
                    ->whereColumn('cart_items.cart_id', 'carts.id');
            })
            ->delete();

        $this->info("Pruned {$deleted} empty guest cart(s) untouched since {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
