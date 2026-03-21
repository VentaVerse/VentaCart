<?php

namespace App\Services\PaymentMethods;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $message = null,
    ) {}
}
