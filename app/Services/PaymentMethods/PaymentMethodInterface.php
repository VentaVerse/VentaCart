<?php

namespace App\Services\PaymentMethods;

use App\Models\Order;

interface PaymentMethodInterface
{
    public function getKey(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function isEnabled(): bool;
    public function processPayment(Order $order): PaymentResult;
}
