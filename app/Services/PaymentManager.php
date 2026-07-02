<?php

namespace App\Services;

use App\Services\PaymentMethods\PaymentMethodInterface;

/**
 * The payment "sign-in sheet" (mirror of CourierManager). Methods self-register
 * from service providers — core registers its three in AppServiceProvider;
 * a 3rd-party extension calls the register() method below in its own provider
 * and needs NO core edits. Registration is explicit and greppable by design —
 * a repo-wide search for calls to this class's register() method enumerates
 * every payment method.
 *
 * A registered class either IS one method (new $class()) or is a FACTORY
 * exposing static instances(): array (e.g. BankTransferPayment — one method
 * per active bank account).
 */
class PaymentManager
{
    /** @var array<int, class-string<PaymentMethodInterface>> */
    private static array $registered = [];

    /** @var PaymentMethodInterface[]|null per-request memo */
    private static ?array $memo = null;

    public static function register(string $class): void
    {
        if (!in_array($class, self::$registered, true)) {
            self::$registered[] = $class;
        }
        self::$memo = null;
    }

    /**
     * All method instances, factories flattened, registration order.
     * Memoized per-request: factories like BankTransferPayment::instances()
     * hit the DB, and methods() may be called many times in a single request
     * (checkout, admin listing, order emails). register()/reset() invalidate.
     * @return PaymentMethodInterface[]
     */
    public static function methods(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $methods = [];
        foreach (self::$registered as $class) {
            if (method_exists($class, 'instances')) {
                foreach ($class::instances() as $m) {
                    $methods[] = $m;
                }
            } else {
                $methods[] = new $class();
            }
        }
        return self::$memo = $methods;
    }

    /** @return PaymentMethodInterface[] */
    public static function enabledMethods(): array
    {
        return array_values(array_filter(self::methods(), fn ($m) => $m->isEnabled()));
    }

    public static function get(string $key): ?PaymentMethodInterface
    {
        foreach (self::methods() as $m) {
            if ($m->getKey() === $key) {
                return $m;
            }
        }
        return null;
    }

    /** Test hook. */
    public static function reset(): void
    {
        self::$registered = [];
        self::$memo = null;
    }
}
