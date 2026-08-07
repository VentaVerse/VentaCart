<?php

namespace App\Support;

class PaymentFamilies
{
    /**
     * Method families the payments UI knows how to render: family key =>
     * [display label default, description]. Shared by the landing page
     * (`PaymentSettingController`, one card per family) and the detail page
     * (`PaymentMethodPageController`, one page per family) so the two
     * surfaces never drift out of sync.
     */
    public const LIST = [
        'bank_transfer' => [
            'default_name' => 'Bank Transfer',
            'description' => 'Multi-account bank transfer with QR codes',
        ],
        'hitpay' => [
            'default_name' => 'HitPay (Card / GCash / GrabPay / Maya)',
            'description' => 'Online payment gateway (credit card, e-wallets)',
        ],
        'cod' => [
            'default_name' => 'Cash on Delivery',
            'description' => 'Customer pays upon delivery',
        ],
    ];
}
