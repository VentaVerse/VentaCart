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
        $classes = self::$registered;
        asort($classes);

        return array_map(fn (string $class) => new $class(), array_keys($classes));
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

    /** Test hook. */
    public static function reset(): void
    {
        self::$registered = [];
    }
}
