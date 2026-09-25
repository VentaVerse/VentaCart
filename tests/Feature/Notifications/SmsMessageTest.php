<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use Extensions\SmsUnisms\UniSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsMessageTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('sms-unisms');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        config(['app.name' => 'VentaCart']);
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
        $this->order = Order::create([
            'store_id' => 1, 'first_name' => 'June', 'last_name' => 'Sillacos',
            'email' => 'june@example.test', 'phone' => '09171234567',
            'order_status_id' => $status->id,
        ]);
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);
    }

    public function test_the_seeded_template_is_the_owners_sentence(): void
    {
        $this->assertSame('{store_name}: Order #{order_id} is now {order_status}', UniSms::template());
    }

    public function test_the_template_is_filled_in(): void
    {
        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($this->order, 'Shipped', null);

        $this->assertSame('VentaCart: Order #' . $this->order->id . ' is now Shipped', $text);
    }

    /** Review Focus 4: a placeholder with nothing behind it prints nothing. */
    public function test_an_empty_placeholder_prints_nothing(): void
    {
        Setting::updateOrCreate(
            ['group' => 'sms', 'key' => UniSms::KEY_TEMPLATE],
            ['value' => 'Order #{order_id} tracking {tracking_number}']
        );

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($this->order, 'Shipped', null);

        $this->assertSame('Order #' . $this->order->id . ' tracking', trim($text));
        $this->assertStringNotContainsString('{tracking_number}', $text);
    }

    /** Review Focus 5: a link typed into the template never reaches a handset. */
    public function test_a_url_in_the_template_is_stripped(): void
    {
        Setting::updateOrCreate(
            ['group' => 'sms', 'key' => UniSms::KEY_TEMPLATE],
            ['value' => 'Track at https://gogoxpress.com/track/ABC123 now']
        );

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($this->order, 'Shipped', null);

        $this->assertStringNotContainsString('http', $text);
        $this->assertStringNotContainsString('gogoxpress.com', $text);
    }

    public function test_a_tracking_link_in_the_comment_becomes_its_number(): void
    {
        $comment = 'QuadX: <a href="https://gogoxpress.com/track/GGX1">GGX1</a>';

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($this->order, 'Shipped', $comment);

        $this->assertStringContainsString('GGX1', $text, 'The number is what a customer can use.');
        $this->assertStringNotContainsString('http', $text);
    }

    /**
     * The brief's setUp installs the extension but saves no credentials, so
     * UniSms::send() would refuse before ever touching the content. This
     * test is about the URL guard inside send(), not about readiness, so it
     * saves a key and sender ID itself to reach that code.
     */
    public function test_the_send_layer_refuses_to_carry_a_url(): void
    {
        Setting::updateOrCreate(
            ['group' => 'sms', 'key' => UniSms::KEY_API],
            ['value' => \Illuminate\Support\Facades\Crypt::encryptString('test-key')]
        );
        Setting::updateOrCreate(['group' => 'sms', 'key' => UniSms::KEY_SENDER], ['value' => 'TEST']);

        UniSms::send('+639171234567', 'See https://example.com for details');

        Http::assertSent(function ($request) {
            return ! str_contains(json_encode($request->data()), 'http');
        });
    }

    /**
     * The guard must not be trigger-happy: an order number, an amount, a
     * sentence full stop, and a name with a dot are not URLs and must reach
     * the handset untouched.
     */
    public function test_ordinary_punctuated_text_survives_the_url_guard(): void
    {
        $text = 'Order #29876 confirmed. Total 1,299.00 paid. Ask for J. Reyes at pickup.';

        $this->assertSame($text, UniSms::stripUrls($text));
    }

    /**
     * Saving the settings form with the template field left blank must fall
     * back to the seeded default, not save an empty string that would leave
     * every future message blank.
     */
    public function test_saving_the_panel_with_an_empty_template_keeps_the_default(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        Setting::updateOrCreate(
            ['group' => 'sms', 'key' => UniSms::KEY_TEMPLATE],
            ['value' => 'A custom sentence with {order_id}']
        );

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
            'template' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame(UniSms::DEFAULT_TEMPLATE, UniSms::template());
    }
}
