<?php

namespace App\Services\PaymentMethods;

use App\Models\Order;
use App\Models\Setting;

class CodPayment implements PaymentMethodInterface
{
    public function getKey(): string
    {
        return 'cod';
    }

    public function getName(): string
    {
        return 'Cash on Delivery';
    }

    public function getDescription(): string
    {
        return 'Pay with cash when your order is delivered.';
    }

    public function getImage(): ?string
    {
        return null;
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::where('group', 'payment')->where('key', 'cod_enabled')->value('value');
    }

    public function getSortOrder(): int
    {
        return (int) Setting::where('group', 'payment')->where('key', 'cod_sort_order')->value('value');
    }

    public function processPayment(Order $order): PaymentResult
    {
        return new PaymentResult(success: true);
    }
}
