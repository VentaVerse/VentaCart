<?php

namespace Extensions\StoreCredit;

use App\Models\Order;
use App\Services\OrderTotals\OrderTotalProviderInterface;
use App\Services\OrderTotals\ReleasableTotalInterface;
use App\Services\OrderTotals\TotalsContext;
use Extensions\StoreCredit\Models\CustomerTransaction;

/**
 * Auto-applies min(balance, what's left of the total) as a negative
 * line. Runs after the coupon provider, so "what's left" is already
 * coupon-discounted. confirm() writes the spend into the ledger inside
 * the placement transaction.
 *
 * An order that is never paid gives its credit back (release()), and one
 * paid after that takes it again (reclaim()). A placed order that is
 * later cancelled is not credited back automatically: the merchant
 * re-credits by hand from the customer's panel.
 */
class StoreCreditTotalProvider implements OrderTotalProviderInterface, ReleasableTotalInterface
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

    public function release(Order $order, array $line): ?string
    {
        $customerId = $this->customerOf($order, $line);
        if ($customerId === null) {
            return null;
        }

        // What the order still holds, read from the ledger now: its spend
        // less anything already credited back for it, by this path or any
        // other. Never more than the line took.
        $held = min($this->appliedCents($line), $this->heldCents($order, $customerId));
        if ($held <= 0) {
            return null;
        }

        CreditService::add($customerId, $held / 100, 'Order #' . $order->id . ' not paid, store credit returned', $order->id);

        return 'Store credit of ' . number_format($held / 100, 2) . ' returned to the customer, as the order was not paid.';
    }

    public function reclaim(Order $order, array $line): ?string
    {
        $customerId = $this->customerOf($order, $line);
        if ($customerId === null) {
            return null;
        }

        $owed = $this->appliedCents($line) - max(0, $this->heldCents($order, $customerId));
        if ($owed <= 0) {
            return null;
        }

        // May take the balance below zero when the returned credit was
        // spent meanwhile; the ledger then shows what the customer owes.
        CreditService::add($customerId, -$owed / 100, 'Order #' . $order->id . ' paid, store credit applied again', $order->id);

        return 'Store credit of ' . number_format($owed / 100, 2) . ' applied again, as the order was paid after it had been returned.';
    }

    private function customerOf(Order $order, array $line): ?int
    {
        $id = $line['extras']['customer_id'] ?? $order->customer_id;

        return $id ? (int) $id : null;
    }

    /** What the line took off the order, in cents. */
    private function appliedCents(array $line): int
    {
        return max(0, (int) round(-(float) ($line['amount'] ?? 0) * 100));
    }

    /** The order's ledger entries for this customer, summed and negated, in cents. */
    private function heldCents(Order $order, int $customerId): int
    {
        $sum = CustomerTransaction::where('order_id', $order->id)
            ->where('customer_id', $customerId)
            ->sum('amount');

        return -(int) round((float) $sum * 100);
    }
}
