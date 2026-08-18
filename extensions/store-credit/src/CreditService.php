<?php

namespace Extensions\StoreCredit;

use Extensions\StoreCredit\Models\CustomerTransaction;

class CreditService
{
    public static function balance(int $customerId): float
    {
        return round((float) CustomerTransaction::where('customer_id', $customerId)->sum('amount'), 2);
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
