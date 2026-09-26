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

    /**
     * What HitPay kept from this payment, or null when they did not say.
     *
     * Their webhook carries no fee at all, so it has to be asked for. The
     * payment request answers with a list of payment attempts, and only the
     * one that succeeded carries the charge worth recording: a failed attempt
     * can carry a figure of its own, and reading the first entry would bill
     * the store for a payment that never happened.
     *
     * Null on anything unclear — HitPay unreachable, a request with nothing
     * succeeded yet, a malformed answer — because the order page and the ERP
     * both read null as "not known yet". Returning 0 would report the payment
     * as free and quietly overstate the margin.
     */
    public function fetchFee(string $paymentRequestId): ?float
    {
        $request = $this->fetchPaymentRequest($paymentRequestId);

        if ($request === null) {
            return null;
        }

        $fee = $this->succeededPayment($request)['fees'] ?? null;

        return is_numeric($fee) ? (float) $fee : null;
    }

    /**
     * Has HitPay itself taken the money for this order?
     *
     * The return page cannot know from its own address: HitPay appends
     * ?status=completed to the redirect, and anyone can type that. So the
     * answer comes from HitPay's API, and it must match this order the same
     * three ways the signed webhook is held to: the payment request is the
     * one this order created, it carries this order's reference, and it was
     * for this order's total. It must also hold a payment that succeeded.
     *
     * False on anything short of that, including HitPay being unreachable
     * or a payment still settling: the order is then left as it is, and the
     * webhook confirms it once the money really lands.
     */
    public function confirmsPayment(Order $order): bool
    {
        $paymentRequestId = (string) $order->payment_reference;
        $request = $this->fetchPaymentRequest($paymentRequestId);

        if ($request === null) {
            return false;
        }

        $matches = ($request['id'] ?? null) === $paymentRequestId
            && ($request['status'] ?? null) === 'completed'
            && (string) ($request['reference_number'] ?? '') === (string) $order->id
            && is_numeric($request['amount'] ?? null)
            && abs((float) $request['amount'] - (float) $order->total) <= 0.01
            && $this->succeededPayment($request) !== null;

        if (! $matches) {
            Log::warning('HitPay: return page refused an unconfirmed payment', [
                'order_id' => $order->id,
                'payment_request' => $paymentRequestId,
                'status' => $request['status'] ?? null,
                'reference_number' => $request['reference_number'] ?? null,
                'amount' => $request['amount'] ?? null,
            ]);
        }

        return $matches;
    }

    /** The payment request as HitPay reports it, or null when it cannot be read. */
    private function fetchPaymentRequest(string $paymentRequestId): ?array
    {
        $mode = $this->getSetting('hitpay_mode', 'sandbox');
        $apiKey = $this->getDecryptedSetting("hitpay_{$mode}_api_key");

        if (! $apiKey || $paymentRequestId === '') {
            return null;
        }

        $base = $mode === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL;

        try {
            $response = Http::withHeaders(['X-BUSINESS-API-KEY' => $apiKey])
                ->timeout(20)
                ->get($base . '/' . rawurlencode($paymentRequestId));

            if (! $response->successful()) {
                Log::warning('HitPay: could not read the payment request', [
                    'payment_request' => $paymentRequestId, 'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('HitPay: payment request lookup failed', [
                'payment_request' => $paymentRequestId, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The attempt that actually took the money. A failed attempt can sit in
     * the same list with figures of its own, so the first entry is not it.
     */
    private function succeededPayment(array $request): ?array
    {
        $payment = collect($request['payments'] ?? [])->firstWhere('status', 'succeeded');

        return is_array($payment) ? $payment : null;
    }

    /**
     * Put the fee on the order, beside the total and never inside it.
     *
     * A fee that could not be read leaves the column alone rather than
     * writing a zero, so a later attempt can still fill it in.
     */
    public function recordFee(Order $order, string $paymentRequestId): void
    {
        $fee = $this->fetchFee($paymentRequestId);

        if ($fee !== null) {
            $order->forceFill(['payment_fee' => $fee])->save();
        }
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
