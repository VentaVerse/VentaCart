<?php

namespace Extensions\PaymentHitpay;

use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HitPayPayment implements PaymentMethodInterface
{
    private const SANDBOX_URL = 'https://api.sandbox.hit-pay.com/v1/payment-requests';
    private const PRODUCTION_URL = 'https://api.hit-pay.com/v1/payment-requests';

    public function getKey(): string
    {
        return 'hitpay';
    }

    public function getBaseKey(): string
    {
        return $this->getKey();
    }

    public function getName(): string
    {
        $custom = $this->getSetting('hitpay_display_name');
        return !empty($custom) ? $custom : 'HitPay (Card / GCash / GrabPay / Maya)';
    }

    public function getDescription(): string
    {
        return 'Pay securely via credit card, GCash, GrabPay, or Maya.';
    }

    public function getImage(): ?string
    {
        return null;
    }

    public function isEnabled(): bool
    {
        // Registration is the switch: this method only exists while its
        // extension is enabled.
        return true;
    }

    public function processPayment(Order $order): PaymentResult
    {
        $mode = $this->getSetting('hitpay_mode', 'sandbox');
        $apiKey = $this->getDecryptedSetting("hitpay_{$mode}_api_key");

        if (!$apiKey) {
            Log::error('HitPay: API key not configured for mode: ' . $mode);
            return new PaymentResult(success: false, message: 'Payment gateway not configured.');
        }

        $apiUrl = $mode === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL;

        $payload = [
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'currency' => \App\Models\Currency::where('is_default', true)->value('code') ?? 'PHP',
            'email' => $order->email,
            'name' => config('app.name', 'Store'),
            'purpose' => 'Order #' . $order->id,
            'reference_number' => (string) $order->id,
            'redirect_url' => route('checkout.hitpay.return'),
            'webhook' => route('hitpay.webhook'),
        ];

        try {
            $response = Http::withHeaders(['X-BUSINESS-API-KEY' => $apiKey])
                ->timeout(30)
                ->post($apiUrl, $payload);

            if (!$response->successful()) {
                $error = $response->json('message', 'Unknown error from payment gateway.');
                Log::error('HitPay API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return new PaymentResult(success: false, message: 'Payment failed: ' . $error);
            }

            $data = $response->json();
            $paymentRequestId = $data['id'] ?? null;
            $paymentUrl = $data['url'] ?? null;

            if (!$paymentRequestId || !$paymentUrl) {
                Log::error('HitPay: Missing id or url in response', ['data' => $data]);
                return new PaymentResult(success: false, message: 'Invalid response from payment gateway.');
            }

            $order->update(['payment_reference' => $paymentRequestId]);

            return new PaymentResult(success: true, redirectUrl: $paymentUrl);

        } catch (\Throwable $e) {
            Log::error('HitPay exception: ' . $e->getMessage());
            return new PaymentResult(success: false, message: 'Could not connect to payment gateway.');
        }
    }

    public static function verifyWebhookSignature(array $data, string $salt): bool
    {
        $hmac = $data['hmac'] ?? '';
        if (empty($hmac) || empty($salt)) {
            return false;
        }

        $params = $data;
        unset($params['hmac']);

        $hmacSource = [];
        foreach ($params as $key => $val) {
            $hmacSource[$key] = "{$key}{$val}";
        }
        ksort($hmacSource);

        $computed = hash_hmac('sha256', implode('', array_values($hmacSource)), $salt);

        return hash_equals($computed, $hmac);
    }

    private function getSetting(string $key, ?string $default = null): ?string
    {
        $value = Setting::where('group', 'payment')->where('key', $key)->value('value');
        return $value ?? $default;
    }

    private function getDecryptedSetting(string $key): ?string
    {
        $encrypted = $this->getSetting($key);
        if (!$encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            Log::error("HitPay: Failed to decrypt {$key}: " . $e->getMessage());
            return null;
        }
    }
}
