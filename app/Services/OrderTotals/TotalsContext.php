<?php

namespace App\Services\OrderTotals;

use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * The accumulator a totals run carries: the order's inputs, the running
 * shipping cost (mutable — free-shipping coupons zero it), and the lines
 * the providers contribute. The grand total is arithmetic over all of
 * it, computed here and nowhere else.
 */
class TotalsContext
{
    /** @var array<int, array{code: string, label: string, amount: float, extras: array}> */
    public array $lines = [];

    public function __construct(
        public readonly ?Customer $customer,
        public readonly Collection $items,
        public readonly float $subtotal,
        public float $shippingCost,
        public readonly array $input = [],
    ) {
    }

    public function addLine(string $code, string $label, float $amount, array $extras = []): void
    {
        $this->lines[] = ['code' => $code, 'label' => $label, 'amount' => round($amount, 2), 'extras' => $extras];
    }

    /** What the customer still owes before this moment's line is added. */
    public function remaining(): float
    {
        return max(0, $this->total());
    }

    public function total(): float
    {
        $total = $this->subtotal + $this->shippingCost;
        foreach ($this->lines as $line) {
            $total += $line['amount'];
        }

        return max(0, round($total, 2));
    }

    public function line(string $code): ?array
    {
        foreach ($this->lines as $line) {
            if ($line['code'] === $code) {
                return $line;
            }
        }

        return null;
    }
}
