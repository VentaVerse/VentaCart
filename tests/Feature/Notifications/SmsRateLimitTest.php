<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendOrderStatusNotification;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use Extensions\SmsUnisms\UniSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

/**
 * UniSMS answers one request every two seconds. A courier poll that moves
 * twenty parcels at once queues twenty texts, and without a throttle most
 * of them came back HTTP 429 and were recorded as not sent, never retried.
 *
 * Core names no gateway: the job asks for a limiter keyed by its target,
 * and only the UniSMS extension defines one. A 429 that still slips through
 * (another process spending the same account) is a "come back later", so
 * the job goes back on the queue instead of writing a failure.
 */
class SmsRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private Order $order;

    private int $historyId;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('sms-unisms');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_ENABLED], ['value' => '1']);
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_API], ['value' => Crypt::encryptString('test-key')]);
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_SENDER], ['value' => 'TEST']);

        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
        \Extensions\SmsUnisms\SmsTemplate::create(['title' => 'Shipped', 'message' => UniSms::DEFAULT_TEMPLATE])
            ->statuses()->sync([$shipped->id]);
        $this->order = Order::create([
            'store_id' => 1, 'first_name' => 'June', 'last_name' => 'Sillacos',
            'email' => 'june@example.test', 'phone' => '+639171234567',
            'order_status_id' => $shipped->id,
        ]);
        $this->historyId = $this->order->histories()->create([
            'order_status_id' => $shipped->id, 'comment' => null, 'notify_customer' => true,
        ])->id;
    }

    protected function tearDown(): void
    {
        $this->resetNotificationChannels();
        parent::tearDown();
    }

    private function job(): SendOrderStatusNotification
    {
        return new SendOrderStatusNotification($this->order->id, 'sms', 'Packing', 'Shipped', null, $this->historyId);
    }

    public function test_the_unisms_extension_defines_the_sms_limiter(): void
    {
        $limiter = RateLimiter::limiter('order-notification:sms');

        $this->assertNotNull($limiter, 'Installing UniSMS must register the throttle its API needs.');
    }

    public function test_core_defines_no_limiter_for_email(): void
    {
        $this->assertNull(RateLimiter::limiter('order-notification:email'), 'RateLimited passes a job straight through when no limiter exists.');
    }

    public function test_the_job_asks_for_a_limiter_keyed_by_its_target(): void
    {
        $job = $this->job()->withFakeQueueInteractions();

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimited::class, $middleware[0]);
        $name = (new \ReflectionProperty(RateLimited::class, 'limiterName'))->getValue($middleware[0]);
        $this->assertSame('order-notification:sms', $name);
    }

    /** A released job consumes an attempt, so a count would run out mid-burst. */
    public function test_the_job_retries_until_a_deadline_rather_than_a_count(): void
    {
        $job = $this->job();

        $this->assertFalse(property_exists($job, 'tries'), 'Released jobs consume tries.');
        $this->assertGreaterThan(now()->getTimestamp(), $job->retryUntil()->getTimestamp());
    }

    public function test_a_429_releases_the_job_instead_of_recording_a_failure(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too many requests'], 429)]);

        $job = $this->job()->withFakeQueueInteractions();
        $job->handle();

        Http::assertSentCount(1);
        $job->assertReleased();
        $this->assertArrayNotHasKey('sms', $this->order->histories()->find($this->historyId)->notifications ?? [], 'Nothing is recorded until the text is really sent or really refused.');
    }

    public function test_any_other_refusal_is_still_recorded_once(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Bad sender'], 422)]);

        $job = $this->job()->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();
        Http::assertSentCount(1);
        $recorded = $this->order->histories()->find($this->historyId)->notifications['sms'];
        $this->assertFalse($recorded['sent']);
    }

    public function test_a_success_is_recorded(): void
    {
        Http::fake(['*' => Http::response(['data' => ['reference_id' => 'msg_1']], 200)]);

        $job = $this->job()->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();
        $this->assertTrue($this->order->histories()->find($this->historyId)->notifications['sms']['sent']);
    }
}
