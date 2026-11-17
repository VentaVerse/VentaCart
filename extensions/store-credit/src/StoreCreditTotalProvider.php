<?php

namespace Extensions\StoreCredit;

use App\Exceptions\OrderTotalChangedException;
use App\Models\Order;
use App\Services\OrderTotals\OrderTotalProviderInterface;
use App\Services\OrderTotals\ReclaimResult;
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
 * paid after that takes it again, as far as the balance covers it
 * (reclaim()).
 * A placed order that is later cancelled is not credited back
 * automatically: the merchant re-credits by hand from the customer's
 * panel.
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
        $customerId = (int) $line['extras']['customer_id'];

        // The line was worked out from the balance before the order
        // transaction began, so another order of this customer's may have
        // spent it since. Read again under the customer's credit lock, the
        // one every balance-dependent spend takes; when it no longer covers
        // the line, the placement is refused and rolled back rather than
        // driving the balance below zero.
        $balance = (int) round(CreditService::lockedBalance($customerId) * 100);
        $spend = (int) round(-(float) $line['amount'] * 100);
        if ($spend > $balance) {
            throw new OrderTotalChangedException('Your store credit balance changed. Please review your order.');
        }

        CreditService::add(
            $customerId,
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

    public function reclaim(Order $order, array $line): ?ReclaimResult
    {
        $customerId = $this->customerOf($order, $line);
        if ($customerId === null) {
            return null;
        }

        $owed = $this->appliedCents($line) - max(0, $this->heldCents($order, $customerId));
        if ($owed <= 0) {
            return null;
        }

        // Taken back from what the customer has now, read under the lock
        // every balance-dependent spend takes, so the balance never goes
        // below zero. What it cannot cover is marked for the merchant.
        $balance = max(0, (int) round(CreditService::lockedBalance($customerId) * 100));
        $taken = min($owed, $balance);
        $short = $owed - $taken;

        if ($taken > 0) {
            CreditService::add($customerId, -$taken / 100, 'Order #' . $order->id . ' paid, store credit applied again', $order->id);
        }

        if ($short === 0) {
            return new ReclaimResult('Store credit of ' . number_format($taken / 100, 2) . ' applied again, as the order was paid after it had been returned.');
        }

        return new ReclaimResult(
            'Paid after its store credit of ' . number_format($owed / 100, 2) . ' was returned. '
                . number_format($taken / 100, 2) . ' was taken back from the customer\'s balance; '
                . number_format($short / 100, 2) . ' was not recovered, as the balance does not cover it.',
            needsReview: true,
        );
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
