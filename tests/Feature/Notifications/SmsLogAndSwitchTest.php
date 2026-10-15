<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendOrderStatusNotification;
use App\Models\Automation;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Extensions\SmsUnisms\SmsChannel;
use Extensions\SmsUnisms\SmsLog;
use Extensions\SmsUnisms\SmsStatusMap;
use Extensions\SmsUnisms\SmsTemplate;
use Extensions\SmsUnisms\UniSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Did the customer get the text?" answered on UniSMS's own page, a switch
 * to pause a template without deleting it, and the queue emptied by cron on
 * hosts that run no worker.
 */
class SmsLogAndSwitchTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('sms-unisms');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        config(['app.name' => 'VentaCart']);
        $this->shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
    }

    private function readyToSend(): void
    {
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_ENABLED], ['value' => '1']);
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_API], ['value' => Crypt::encryptString('secret-key-123')]);
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_SENDER], ['value' => 'NGD']);
    }

    private function order(?string $phone = '+639171234567'): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'June', 'last_name' => 'Reyes',
            'email' => 'june@example.test', 'phone' => $phone, 'order_status_id' => $this->shipped->id,
        ]);
    }

    private function template(bool $active = true): SmsTemplate
    {
        return SmsTemplate::create([
            'order_status_id' => $this->shipped->id, 'title' => 'Shipped',
            'message' => "Order #{order_id}\nis now {order_status}", 'is_active' => $active,
        ]);
    }

    public function test_a_sent_text_is_logged_with_its_payload_and_response(): void
    {
        $this->readyToSend();
        Http::fake(['*' => Http::response(['data' => ['reference_id' => 'msg_42', 'status' => 'queued']], 200)]);
        $template = $this->template();
        $order = $this->order();

        (new SmsChannel())->statusChanged($order, 'Packing', 'Shipped', null, $this->shipped->id);

        $log = SmsLog::sole();
        $this->assertSame('sent', $log->status);
        $this->assertSame('status_change', $log->source);
        $this->assertSame($order->id, (int) $log->order_id);
        $this->assertSame($template->id, (int) $log->sms_template_id);
        $this->assertSame('+639171234567', $log->recipient);
        $this->assertSame('NGD', $log->sender_id);
        $this->assertSame("Order #{$order->id}\nis now Shipped", $log->message);
        $this->assertSame(200, (int) $log->http_status);
        $this->assertSame('msg_42', $log->gateway_reference);
        $this->assertStringContainsString('msg_42', $log->response);
    }

    public function test_the_api_key_never_reaches_the_log(): void
    {
        $this->readyToSend();
        Http::fake(['*' => Http::response(['message' => 'bad key'], 401)]);

        UniSms::send('+639171234567', 'Hello', null, ['source' => 'test']);

        $this->assertStringNotContainsString('secret-key-123', json_encode(SmsLog::sole()->toArray()));
    }

    public function test_a_refusal_is_logged_with_what_unisms_said(): void
    {
        $this->readyToSend();
        Http::fake(['*' => Http::response(['message' => 'Sender ID not approved'], 422)]);

        UniSms::send('+639171234567', 'Hello', null, ['source' => 'test']);

        $log = SmsLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertSame(422, (int) $log->http_status);
        $this->assertStringContainsString('Sender ID not approved', $log->error);
        $this->assertStringContainsString('Sender ID not approved', $log->response);
    }

    public function test_being_slowed_down_is_logged_as_throttled(): void
    {
        $this->readyToSend();
        Http::fake(['*' => Http::response(['message' => 'Too many'], 429)]);

        UniSms::send('+639171234567', 'Hello');

        $this->assertSame('throttled', SmsLog::sole()->status);
    }

    public function test_a_send_refused_before_reaching_unisms_is_logged_too(): void
    {
        Http::fake();

        UniSms::send('+639171234567', 'Hello', null, ['source' => 'test']);

        $log = SmsLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertNull($log->http_status);
        $this->assertSame('No API key saved.', $log->error);
        Http::assertNothingSent();
    }

    public function test_an_order_with_no_number_is_logged(): void
    {
        $this->readyToSend();
        Http::fake();
        $this->template();

        (new SmsChannel())->statusChanged($this->order(null), 'Packing', 'Shipped', null, $this->shipped->id);

        $log = SmsLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('No mobile number', $log->error);
        Http::assertNothingSent();
    }

    public function test_the_test_button_logs_as_a_test(): void
    {
        $this->readyToSend();
        Http::fake(['*' => Http::response(['data' => ['reference_id' => 'msg_1']], 200)]);
        $this->actingAs(User::factory()->create());

        $this->postJson(route('admin.extensions.unisms.send-test'), ['recipient' => '+639171234567', 'content' => 'Test'])->assertOk();

        $this->assertSame('test', SmsLog::sole()->source);
    }

    public function test_a_switched_off_template_sends_nothing(): void
    {
        $this->readyToSend();
        Http::fake();
        $this->template(active: false);

        $this->assertFalse((new SmsChannel())->wantsStatus($this->shipped->id));
        $this->assertSame([], SmsStatusMap::all());

        $result = (new SmsChannel())->statusChanged($this->order(), 'Packing', 'Shipped', null, $this->shipped->id);

        $this->assertFalse($result['sent']);
        Http::assertNothingSent();
    }

    public function test_the_page_saves_the_switch(): void
    {
        $this->actingAs(User::factory()->create());
        $template = $this->template();

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'NGD',
            'templates' => [[
                'id' => $template->id, 'title' => 'Shipped', 'message' => 'x',
                'status_id' => $this->shipped->id, 'is_active' => '0',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_the_log_tab_lists_attempts_and_counts_texts_still_waiting(): void
    {
        $this->actingAs(User::factory()->create());
        config(['queue.default' => 'database']);
        SmsLog::create(['source' => 'test', 'recipient' => '+639171234567', 'message' => 'Hello from the log',
            'status' => 'failed', 'error' => 'Sender ID not approved']);
        SendOrderStatusNotification::dispatch(1, 'sms', 'Packing', 'Shipped', null, null, $this->shipped->id);
        SendOrderStatusNotification::dispatch(1, 'email', 'Packing', 'Shipped', null, null, $this->shipped->id);

        $page = $this->get(route('admin.extensions.unisms.index', ['tab' => 'log']))->assertOk();

        $page->assertSee('Hello from the log')->assertSee('Sender ID not approved');
        $this->assertSame(1, $page->viewData('waiting')['count'], 'Only the text counts, not the email.');
        $page->assertSee('1 text waiting to send');
    }

    public function test_a_store_with_no_worker_gets_a_cron_job_that_empties_the_queue(): void
    {
        $row = Automation::where('key', 'queue_work')->first();

        $this->assertNotNull($row, 'Added by migration, so stores installed earlier get it too.');
        $this->assertTrue($row->enabled);
        $this->assertStringStartsWith('queue:drain', $row->command);
        $this->assertSame('* * * * *', $row->cron_expression);
    }

    /** The whole path on a host with no worker: queued by a status change, sent by the cron's run. */
    public function test_the_cron_run_sends_a_waiting_text(): void
    {
        $this->readyToSend();
        config(['queue.default' => 'database']);
        Http::fake(['*' => Http::response(['data' => ['reference_id' => 'msg_7']], 200)]);
        $this->template();
        $packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 2]);
        $order = $this->order();
        $order->update(['order_status_id' => $packing->id]);

        \App\Services\OrderStatusChanger::apply($order, $this->shipped->id, 'Handed to courier');

        Http::assertNothingSent();
        $this->assertSame(1, \DB::table('jobs')->count(), 'Queued, not sent, until something works the queue.');

        $this->artisan('queue:drain', ['--max-time' => 50])->assertSuccessful();

        $this->assertSame(0, \DB::table('jobs')->count());
        $this->assertSame('sent', SmsLog::sole()->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sms'));
    }
}
