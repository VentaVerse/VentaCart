<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use Extensions\PaymentHitpay\HitPayPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What HitPay kept.
 *
 * Their webhook does not carry the fee, so it has to be asked for: GET
 * /v1/payment-requests/{id} answers with a payments array, and the succeeded
 * one carries `fees`. The payment request id is already on the order as
 * payment_reference, and the webhook already verifies it matches, so nothing
 * new has to be remembered to make the call.
 */
class HitPayFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('payment-hitpay');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        Setting::create(['group' => 'payment', 'key' => 'hitpay_mode', 'value' => 'sandbox']);
        Setting::create(['group' => 'payment', 'key' => 'hitpay_sandbox_api_key', 'value' => Crypt::encryptString('k')]);
    }

    private function order(): Order
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1]);

        return Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $status->id,
            'total' => 4715.00, 'payment_reference' => 'pr_abc123',
        ]);
    }

    public function test_the_fee_is_read_from_the_succeeded_payment(): void
    {
        Http::fake(['*/payment-requests/pr_abc123' => Http::response([
            'id' => 'pr_abc123',
            'status' => 'completed',
            'amount' => '4715.00',
            'payments' => [
                ['id' => 'p_failed', 'status' => 'failed', 'fees' => '999.00'],
                ['id' => 'p_ok', 'status' => 'succeeded', 'fees' => '170.02'],
            ],
        ], 200)]);

        $this->assertSame(170.02, (new HitPayPayment())->fetchFee('pr_abc123'));
    }

    /** A request with no succeeded payment has no fee to report. */
    public function test_nothing_is_reported_when_no_payment_succeeded(): void
    {
        Http::fake(['*/payment-requests/pr_abc123' => Http::response([
            'id' => 'pr_abc123', 'status' => 'pending',
            'payments' => [['id' => 'p1', 'status' => 'pending', 'fees' => '0']],
        ], 200)]);

        $this->assertNull((new HitPayPayment())->fetchFee('pr_abc123'));
    }

    /** HitPay being unreachable must not be recorded as a zero fee. */
    public function test_a_failed_lookup_reports_nothing_rather_than_zero(): void
    {
        Http::fake(['*/payment-requests/*' => Http::response(['message' => 'nope'], 500)]);

        $this->assertNull((new HitPayPayment())->fetchFee('pr_abc123'));
    }

    /** The fee lands on the order, and the total is untouched. */
    public function test_the_webhook_records_the_fee_without_moving_the_total(): void
    {
        $order = $this->order();

        Http::fake(['*/payment-requests/pr_abc123' => Http::response([
            'id' => 'pr_abc123', 'status' => 'completed',
            'payments' => [['id' => 'p_ok', 'status' => 'succeeded', 'fees' => '170.02']],
        ], 200)]);

        (new HitPayPayment())->recordFee($order, 'pr_abc123');

        $order->refresh();
        $this->assertSame(170.02, (float) $order->payment_fee);
        $this->assertSame(4715.0, (float) $order->total, 'the total must not move');
    }
}
