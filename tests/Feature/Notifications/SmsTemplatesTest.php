<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Extensions\SmsUnisms\SmsChannel;
use Extensions\SmsUnisms\SmsStatusMap;
use Extensions\SmsUnisms\SmsTemplate;
use Extensions\SmsUnisms\UniSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A message per order status, written as named templates.
 *
 * The case that asked for it: "Tracking no. {tracking_number}" belongs on
 * Shipped and reads as nonsense on Pending, so one sentence for every status
 * cannot work.
 */
class SmsTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $pending;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('sms-unisms');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        config(['app.name' => 'VentaCart']);

        $this->pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);
    }

    private function template(string $title, string $message, array $statusIds): SmsTemplate
    {
        $template = SmsTemplate::create(['title' => $title, 'message' => $message]);
        $template->statuses()->sync($statusIds);

        return $template;
    }

    private function readyToSend(): void
    {
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_ENABLED], ['value' => '1']);
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_API], ['value' => Crypt::encryptString('test-key')]);
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_SENDER], ['value' => 'TEST']);
        Http::fake(['*' => Http::response(['data' => ['reference_id' => 'msg_1']], 200)]);
    }

    private function order(OrderStatus $status): Order
    {
        return Order::create([
            'store_id' => 1, 'first_name' => 'June', 'last_name' => 'Reyes',
            'email' => 'june@example.test', 'phone' => '+639171234567',
            'order_status_id' => $status->id,
        ]);
    }

    /** @return list<string> every message body handed to the gateway */
    private function sentContents(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]->data()['content'] ?? null)->filter()->values()->all();
    }

    public function test_nothing_texts_until_a_template_claims_the_status(): void
    {
        $this->assertFalse(SmsStatusMap::texts($this->shipped->id));
        $this->assertFalse((new SmsChannel())->wantsStatus($this->shipped->id));
        $this->assertFalse(SmsStatusMap::texts(null));
    }

    public function test_a_template_makes_its_statuses_text(): void
    {
        $this->template('Shipped', 'Order #{order_id} shipped', [$this->shipped->id]);

        $this->assertTrue((new SmsChannel())->wantsStatus($this->shipped->id));
        $this->assertFalse((new SmsChannel())->wantsStatus($this->pending->id));
        $this->assertSame([$this->shipped->id], SmsStatusMap::all());
    }

    public function test_each_status_sends_its_own_templates_message(): void
    {
        $this->readyToSend();
        $this->template('Received', '{store_name}: We got order #{order_id}.', [$this->pending->id]);
        $this->template('Shipped', '{store_name}: Order #{order_id} shipped. Tracking no. {tracking_number}', [$this->shipped->id]);
        $order = $this->order($this->pending);

        (new SmsChannel())->statusChanged($order, null, 'Pending', null, $this->pending->id);

        $this->assertSame(['VentaCart: We got order #' . $order->id . '.'], $this->sentContents());
    }

    public function test_line_breaks_in_a_template_reach_the_handset(): void
    {
        $this->readyToSend();
        $this->template('Shipped', "{store_name}\nOrder #{order_id} shipped.\n\nThank you!", [$this->shipped->id]);
        $order = $this->order($this->shipped);

        (new SmsChannel())->statusChanged($order, 'Pending', 'Shipped', null, $this->shipped->id);

        $this->assertSame(["VentaCart\nOrder #" . $order->id . " shipped.\n\nThank you!"], $this->sentContents());
    }

    public function test_a_caller_with_only_the_status_name_still_finds_the_template(): void
    {
        $this->readyToSend();
        $this->template('Shipped', 'Shipped #{order_id}', [$this->shipped->id]);
        $order = $this->order($this->shipped);

        (new SmsChannel())->statusChanged($order, 'Pending', 'Shipped');

        $this->assertSame(['Shipped #' . $order->id], $this->sentContents());
    }

    public function test_a_status_with_no_template_sends_nothing_even_when_asked(): void
    {
        $this->readyToSend();

        $result = (new SmsChannel())->statusChanged($this->order($this->pending), null, 'Pending', null, $this->pending->id);

        $this->assertFalse($result['sent']);
        Http::assertNothingSent();
    }

    public function test_rendering_keeps_the_merchants_layout_and_tidies_only_substitution(): void
    {
        $text = UniSms::render("Hi {customer_name},\r\n\r\nTracking {tracking_number} via {courier_name}\nSee newgearday.com/t now", [
            'customer_name' => 'June', 'tracking_number' => '', 'courier_name' => 'SPX',
        ]);

        $this->assertSame("Hi June,\n\nTracking via SPX\nSee now", $text);
    }

    public function test_the_merchants_own_spacing_is_sent_as_typed(): void
    {
        $text = UniSms::render("Order #{order_id}\n  Thank you!  See you soon.\n\n\n- {store_name}", [
            'order_id' => '7', 'store_name' => 'NGD',
        ]);

        $this->assertSame("Order #7\n  Thank you!  See you soon.\n\n\n- NGD", $text);
    }

    public function test_the_page_creates_templates_with_their_statuses(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
            'templates' => [
                ['title' => 'Shipped', 'message' => "Line one\r\nLine two", 'status_ids' => [$this->shipped->id]],
            ],
        ])->assertSessionHasNoErrors();

        $template = SmsTemplate::sole();
        $this->assertSame('Shipped', $template->title);
        $this->assertSame("Line one\nLine two", $template->message, 'A browser posts CRLF; a handset counts one character.');
        $this->assertSame([$this->shipped->id], SmsStatusMap::all());
    }

    public function test_the_page_edits_and_deletes_templates(): void
    {
        $this->actingAs(User::factory()->create());
        $keep = $this->template('Old title', 'Old', [$this->pending->id]);
        $this->template('Gone', 'Bye', [$this->shipped->id]);

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
            'templates' => [
                ['id' => $keep->id, 'title' => 'New title', 'message' => 'New', 'status_ids' => [$this->pending->id, $this->shipped->id]],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, SmsTemplate::count());
        $this->assertSame('New title', $keep->fresh()->title);
        $this->assertSame('New', SmsStatusMap::messageFor($this->shipped->id), 'The status moved over in the same save.');
    }

    public function test_saving_with_no_templates_stops_every_text(): void
    {
        $this->actingAs(User::factory()->create());
        $this->template('Shipped', 'x', [$this->shipped->id]);

        $this->post(route('admin.extensions.unisms.update'), ['sender_id' => 'TEST'])->assertSessionHasNoErrors();

        $this->assertSame(0, SmsTemplate::count());
        $this->assertSame([], SmsStatusMap::all());
    }

    public function test_a_status_cannot_belong_to_two_templates(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
            'templates' => [
                ['title' => 'A', 'message' => 'a', 'status_ids' => [$this->shipped->id]],
                ['title' => 'B', 'message' => 'b', 'status_ids' => [$this->shipped->id]],
            ],
        ])->assertSessionHasErrors('templates.1.status_ids.0');

        $this->assertSame(0, SmsTemplate::count());
    }

    public function test_a_template_needs_a_title_a_message_and_a_status(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
            'templates' => [['title' => '', 'message' => '', 'status_ids' => []]],
        ])->assertSessionHasErrors(['templates.0.title', 'templates.0.message', 'templates.0.status_ids']);
    }

    public function test_an_unknown_status_id_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
            'templates' => [['title' => 'A', 'message' => 'a', 'status_ids' => [999999]]],
        ])->assertSessionHasErrors('templates.0.status_ids.0');
    }

    public function test_the_preview_shows_the_text_as_sent_and_what_it_costs(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('admin.extensions.unisms.preview'), [
            'message' => "{store_name}\nOrder #{order_id} is now {order_status}",
            'status_id' => $this->shipped->id,
        ])->assertOk()->assertExactJson([
            'text' => "VentaCart\nOrder #10482 is now Shipped",
            'chars' => 37,
            'segments' => 1,
            'unicode' => false,
        ]);
    }

    public function test_the_preview_needs_a_signed_in_admin(): void
    {
        $this->postJson(route('admin.extensions.unisms.preview'), ['message' => 'x'])->assertUnauthorized();
    }

    public function test_segments_follow_the_telco_alphabets(): void
    {
        $this->assertSame(1, UniSms::segments(str_repeat('a', 160))['segments']);
        $this->assertSame(2, UniSms::segments(str_repeat('a', 161))['segments']);
        $this->assertSame(1, UniSms::segments(str_repeat('a', 159) . "\n")['segments'], 'A line break is one character.');
        $this->assertSame(2, UniSms::segments(str_repeat('a', 159) . '{')['segments'], 'A brace is sent as two.');

        $unicode = UniSms::segments(str_repeat('a', 70) . '’');
        $this->assertTrue($unicode['unicode']);
        $this->assertSame(2, $unicode['segments'], 'A curly quote sends the whole message as Unicode: 70 per text.');
    }

    public function test_the_page_lists_saved_templates(): void
    {
        $this->actingAs(User::factory()->create());
        $this->template('Out for delivery', 'x', [$this->shipped->id]);

        $this->get(route('admin.extensions.unisms.index'))->assertOk()->assertSee('Out for delivery');
    }
}
