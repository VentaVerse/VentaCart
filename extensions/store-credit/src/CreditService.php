<?php

namespace Extensions\StoreCredit;

use App\Models\Customer;
use Extensions\StoreCredit\Models\CustomerTransaction;

class CreditService
{
    public static function balance(int $customerId): float
    {
        return round((float) CustomerTransaction::where('customer_id', $customerId)->sum('amount'), 2);
    }

    /**
     * The balance, read while holding the customer's row lock until the
     * surrounding transaction ends. Every spend that depends on the
     * balance reads it this way, so two of them for one customer run one
     * after the other and never both spend the same credit. Call inside a
     * transaction.
     */
    public static function lockedBalance(int $customerId): float
    {
        Customer::whereKey($customerId)->lockForUpdate()->value('id');

        return self::balance($customerId);
    }

    /** Signed: positive grants credit, negative spends it. */
    public static function add(int $customerId, float $amount, string $description, ?int $orderId = null, ?int $createdBy = null): CustomerTransaction
    {
        return CustomerTransaction::create([
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'created_by' => $createdBy,
            'description' => $description,
            'amount' => round($amount, 2),
            'created_at' => now(),
        ]);
    }
}
