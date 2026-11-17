<?php

namespace App\Services\OrderTotals;

use App\Models\Order;

/**
 * An order-total provider whose line holds something of the customer's
 * from the moment the order is written, before it is paid (store credit
 * taken off the total). Implement it beside OrderTotalProviderInterface
 * so an order that is never paid gives that back, and an order paid after
 * all takes it again.
 *
 * Both methods are called inside a transaction with the order's row
 * locked, so two paths reaching the same order (a payment webhook, the
 * shopper's browser, an admin) run one after the other. Each must decide
 * from what is stored at that moment, never from a flag remembered
 * elsewhere, so a second call, or a call after other code already gave
 * something back, does nothing more.
 */
interface ReleasableTotalInterface
{
    /**
     * The order will not be paid: give back what $line still holds.
     *
     * @param  array  $line  the line this provider added, as stored on the order
     * @return string|null a note for the order's history, or null when nothing was held
     */
    public function release(Order $order, array $line): ?string;

    /**
     * The order was paid after all: take again whatever release() gave
     * back, so the line's discount is never had twice. When it cannot be
     * taken back in full (the customer has spent it meanwhile), take
     * nothing and answer with needsReview, so the merchant settles it.
     *
     * @param  array  $line  the line this provider added, as stored on the order
     * @return ReclaimResult|null null when nothing was owed
     */
    public function reclaim(Order $order, array $line): ?ReclaimResult;
}
