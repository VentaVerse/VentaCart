<?php

namespace Extensions\PaymentHitpay\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Setting;
use App\Services\OrderStatusChanger;
use App\Support\NotifyChoice;
use Extensions\PaymentHitpay\HitPayPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HitPayWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->all();

        Log::info('HitPay webhook received', ['reference' => $data['reference_number'] ?? 'none']);

        // Get salt for current mode
        $mode = Setting::where('group', 'payment')->where('key', 'hitpay_mode')->value('value') ?? 'sandbox';
        $encryptedSalt = Setting::where('group', 'payment')->where('key', "hitpay_{$mode}_salt")->value('value');

        if (!$encryptedSalt) {
            Log::error('HitPay webhook: Salt not configured for mode ' . $mode);
            return response('Configuration error', 500);
        }

        try {
            $salt = Crypt::decryptString($encryptedSalt);
        } catch (\Throwable $e) {
            Log::error('HitPay webhook: Failed to decrypt salt');
            return response('Configuration error', 500);
        }

        // Verify HMAC signature
        if (!HitPayPayment::verifyWebhookSignature($data, $salt)) {
            Log::warning('HitPay webhook: Invalid signature', ['data' => $data]);
            return response('Invalid signature', 401);
        }

        // Find order
        $orderId = $data['reference_number'] ?? null;
        $paymentRequestId = $data['payment_request_id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$orderId || !$paymentRequestId) {
            Log::warning('HitPay webhook: Missing reference_number or payment_request_id');
            return response('Missing data', 400);
        }

        $order = Order::where('id', $orderId)
            ->where('store_id', (int) config('app.store_id', 1))
            ->first();

        if (!$order) {
            Log::warning('HitPay webhook: Order not found', ['order_id' => $orderId]);
            return response('Order not found', 404);
        }

        // Verify payment_reference matches
        if ($order->payment_reference !== $paymentRequestId) {
            Log::warning('HitPay webhook: payment_request_id mismatch', [
                'expected' => $order->payment_reference,
                'received' => $paymentRequestId,
            ]);
            return response('Reference mismatch', 400);
        }

        // Verify amount matches
        $webhookAmount = (float) ($data['amount'] ?? 0);
        $orderTotal = (float) $order->total;
        if (abs($webhookAmount - $orderTotal) > 0.01) {
            Log::warning('HitPay webhook: Amount mismatch', [
                'order_total' => $orderTotal,
                'webhook_amount' => $webhookAmount,
            ]);
            return response('Amount mismatch', 400);
        }

        // Process based on status
        if ($status === 'completed') {
            $this->handleCompleted($order);
        } elseif (in_array($status, ['failed', 'expired'])) {
            $this->handleFailed($order, $status);
        }

        return response('OK', 200);
    }

    private function handleCompleted(Order $order): void
    {
        // What HitPay kept. Asked for here because their webhook does not
        // carry it, and asked for BEFORE the idempotency check below: a
        // retried webhook is exactly when a fee that could not be read the
        // first time gets a second chance, and skipping early would strand
        // the order without one forever.
        //
        // Never fatal. A fee that cannot be read leaves the column null, and
        // the payment still completes: the customer has paid either way, and
        // refusing to confirm that over a bookkeeping figure would be worse
        // than not knowing the figure.
        if ($order->payment_fee === null && $order->payment_reference) {
            (new \Extensions\PaymentHitpay\HitPayPayment())->recordFee($order, (string) $order->payment_reference);
        }

        $successStatusId = Setting::where('group', 'payment')
            ->where('key', 'hitpay_success_status_id')
            ->value('value');

        // Idempotency: skip if already confirmed at target status
        if ($successStatusId && $order->order_status_id !== null && (int) $order->order_status_id === (int) $successStatusId) {
            return;
        }

        if ($successStatusId) {
            OrderStatusChanger::apply(
                $order,
                (int) $successStatusId,
                'Payment confirmed via HitPay.',
                // The confirmation email below is this path's own; channels
                // still follow the store's ticks.
                NotifyChoice::emailHandledElsewhere(),
            );
        }

        // Clear customer cart
        $cart = $order->customer?->cart;
        if ($cart && $cart->store_id === $order->store_id) {
            $cart->items()->delete();
        }

        // Send confirmation + admin notification
        $order->load('products', 'status');
        \App\Services\CheckoutService::sendOrderConfirmationEmail($order);
        \App\Services\CheckoutService::sendAdminOrderNotification($order);
    }

    private function handleFailed(Order $order, string $status): void
    {
        $comment = $status === 'expired'
            ? 'Payment expired via HitPay.'
            : 'Payment failed via HitPay.';

        OrderHistory::create([
            'order_id' => $order->id,
            'order_status_id' => $order->order_status_id,
            'comment' => $comment,
        ]);
    }
}
