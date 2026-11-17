<?php

namespace App\Services\OrderTotals;

use App\Models\Order;

/**
 * The order-total sign-in sheet (mirror of PaymentManager /
 * ShippingQuoteService / CourierManager). Total extensions register
 * their provider with a sequence; checkout runs them in order over one
 * TotalsContext. A repo-wide search for calls to register() enumerates
 * every total modifier.
 */
class OrderTotals
{
    /** @var array<class-string<OrderTotalProviderInterface>, int> class => sequence */
    private static array $registered = [];

    public static function register(string $class, int $sequence): void
    {
        self::$registered[$class] = $sequence;
    }

    /** @return OrderTotalProviderInterface[] in sequence order */
    public static function providers(): array
    {
        $overrides = self::sortOverrides();

        $entries = [];
        foreach (self::$registered as $class => $sequence) {
            $provider = new $class();
            $entries[] = [$provider, $overrides[$provider->getKey()] ?? $sequence];
        }
        usort($entries, fn (array $a, array $b) => $a[1] <=> $b[1]);

        return array_map(fn (array $e) => $e[0], $entries);
    }

    /**
     * Merchant sort-order overrides (OC parity: each Order Totals
     * extension's settings page carries an editable "Sort order"; lower
     * runs first). Stored as settings group 'totals', key
     * '{provider key}_sort_order'; the provider-registered sequence is
     * the default when no override is saved.
     *
     * @return array<string, int> provider key => sort order
     */
    private static function sortOverrides(): array
    {
        try {
            return \App\Models\Setting::where('group', 'totals')->get()
                ->filter(fn ($s) => str_ends_with($s->key, '_sort_order'))
                ->mapWithKeys(fn ($s) => [substr($s->key, 0, -strlen('_sort_order')) => (int) $s->value])
                ->all();
        } catch (\Throwable) {
            // No DB (early boot, some console contexts): registered order.
            return [];
        }
    }

    /** Run every provider over the context; returns the same context. */
    public static function collect(TotalsContext $ctx): TotalsContext
    {
        foreach (self::providers() as $provider) {
            $provider->collect($ctx);
        }

        return $ctx;
    }

    /**
     * The order exists: hand each contributed line back to its provider
     * for side effects. Called inside the placement transaction.
     */
    public static function confirm(Order $order, array $lines): void
    {
        $byKey = [];
        foreach (self::providers() as $provider) {
            $byKey[$provider->getKey()] = $provider;
        }
        foreach ($lines as $line) {
            if (isset($byKey[$line['code']])) {
                $byKey[$line['code']]->confirm($order, $line);
            }
        }
    }

    /**
     * The order will not be paid: each releasable line gives back what it
     * still holds. Call with the order's row locked, inside a transaction.
     *
     * @return list<string> notes for the order's history
     */
    public static function release(Order $order): array
    {
        $notes = [];
        foreach (self::releasableLines($order) as [$provider, $line]) {
            $note = $provider->release($order, $line);
            if ($note !== null && $note !== '') {
                $notes[] = $note;
            }
        }

        return $notes;
    }

    /**
     * The order was paid after it had been given up on: each releasable
     * line takes again what it gave back. Call with the order's row
     * locked, inside a transaction.
     *
     * @return list<ReclaimResult>
     */
    public static function reclaim(Order $order): array
    {
        $results = [];
        foreach (self::releasableLines($order) as [$provider, $line]) {
            $result = $provider->reclaim($order, $line);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /** @return list<array{0: ReleasableTotalInterface, 1: array}> */
    private static function releasableLines(Order $order): array
    {
        $lines = array_filter(
            (array) ($order->total_lines ?? []),
            fn ($line) => is_array($line) && isset($line['code']),
        );
        if ($lines === []) {
            return [];
        }

        $byKey = [];
        foreach (self::providers() as $provider) {
            if ($provider instanceof ReleasableTotalInterface) {
                $byKey[$provider->getKey()] = $provider;
            }
        }

        $out = [];
        foreach ($lines as $line) {
            if (isset($byKey[$line['code']])) {
                $out[] = [$byKey[$line['code']], $line];
            }
        }

        return $out;
    }

    /** Test hook. */
    public static function reset(): void
    {
        self::$registered = [];
    }
}
