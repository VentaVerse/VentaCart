<?php

namespace Extensions\StoreCredit;

use App\Models\Order;
use App\Services\OrderTotals\OrderTotalProviderInterface;
use App\Services\OrderTotals\TotalsContext;

/**
 * Auto-applies min(balance, what's left of the total) as a negative
 * line, OpenCart credit semantics. Runs after the coupon provider, so
 * "what's left" is already coupon-discounted. confirm() writes the
 * spend into the ledger inside the placement transaction; there is
 * deliberately NO auto-restore when an order is cancelled (OC parity,
 * v1) — the merchant re-credits by hand from the customer's panel.
 */
class StoreCreditTotalProvider implements OrderTotalProviderInterface
{
    public function getKey(): string
    {
        return 'store_credit';
    }

    public function collect(TotalsContext $ctx): void
    {
        if (! $ctx->customer) {
            return;
        }

        $balance = CreditService::balance($ctx->customer->id);
        if ($balance <= 0) {
            return;
        }

        $applied = min($balance, $ctx->remaining());
        if ($applied <= 0) {
            return;
        }

        $ctx->addLine('store_credit', 'Store Credit', -$applied, [
            'customer_id' => $ctx->customer->id,
        ]);
    }

    public function confirm(Order $order, array $line): void
    {
        CreditService::add(
            $line['extras']['customer_id'],
            $line['amount'],
            'Order #' . $order->id,
            $order->id,
        );
    }
}
