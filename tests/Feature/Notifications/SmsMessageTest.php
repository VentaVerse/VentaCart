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

    public function test_the_template_is_filled_in(): void
    {
        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose(UniSms::DEFAULT_TEMPLATE, $this->order, 'Shipped', null);

        $this->assertSame('VentaCart: Order #' . $this->order->id . ' is now Shipped', $text);
    }

    /** Review Focus 4: a placeholder with nothing behind it prints nothing. */
    public function test_an_empty_placeholder_prints_nothing(): void
    {
        $template = 'Order #{order_id} tracking {tracking_number}';

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($template, $this->order, 'Shipped', null);

        $this->assertSame('Order #' . $this->order->id . ' tracking', trim($text));
        $this->assertStringNotContainsString('{tracking_number}', $text);
    }

    /** Review Focus 5: a link typed into the template never reaches a handset. */
    public function test_a_url_in_the_template_is_stripped(): void
    {
        $template = 'Track at https://gogoxpress.com/track/ABC123 now';

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($template, $this->order, 'Shipped', null);

        $this->assertStringNotContainsString('http', $text);
        $this->assertStringNotContainsString('gogoxpress.com', $text);
    }

    /**
     * The merchant's template is authoritative: {comment} only appears when
     * the template names it. This one does, so the anchor in the comment
     * flattens to its tracking number rather than a URL.
     */
    public function test_a_tracking_link_in_the_comment_becomes_its_number(): void
    {
        $template = 'Order #{order_id} is now {order_status}. {comment}';
        $comment = 'QuadX: <a href="https://gogoxpress.com/track/GGX1">GGX1</a>';

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($template, $this->order, 'Shipped', $comment);

        $this->assertStringContainsString('GGX1', $text, 'The number is what a customer can use.');
        $this->assertStringNotContainsString('http', $text);
    }

    /**
     * Review Finding 1: the default template has no {comment}, so a status
     * change carrying a comment must not leak it into the text. Nothing is
     * appended on top of the owner's exact sentence.
     */
    public function test_the_default_template_ignores_a_comment_that_is_present(): void
    {
        $comment = 'QuadX: <a href="https://gogoxpress.com/track/GGX1">GGX1</a>';

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose(UniSms::DEFAULT_TEMPLATE, $this->order, 'Shipped', $comment);

        $this->assertSame('VentaCart: Order #' . $this->order->id . ' is now Shipped', $text);
        $this->assertStringNotContainsString('GGX1', $text);
        $this->assertStringNotContainsString('QuadX', $text);
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
     * the handset untouched. Round 2's finding: a title glued to a name with
     * no space, as is routine in Philippine usage ("Ma.Cristina"), is not a
     * host either, because its last label is neither a country code nor one
     * of the listed generic endings.
     */
    public function test_ordinary_punctuated_text_survives_the_url_guard(): void
    {
        $text = 'Order #29876 confirmed. Total 1,299.00 paid. Ask for J. Reyes at pickup.';

        $this->assertSame($text, UniSms::stripUrls($text));

        $this->assertSame('Ask Mr.Reyes to confirm.', UniSms::stripUrls('Ask Mr.Reyes to confirm.'));
        $this->assertSame('For Ma.Cristina, please call.', UniSms::stripUrls('For Ma.Cristina, please call.'));
        $this->assertSame('See Dr.Santos at the clinic.', UniSms::stripUrls('See Dr.Santos at the clinic.'));
        $this->assertSame('Meet at St.Paul church.', UniSms::stripUrls('Meet at St.Paul church.'));
    }

    /**
     * Review Finding 2 and 3: an allowlist of TLDs let a shortener and an
     * unlisted ending straight through, and a multi-label host left its path
     * behind as a fragment. The guard matches shape, not a list, and drops
     * the whole token, so none of these bypasses survive and none leave a
     * leftover fragment. Round 2 narrowed the bare-host rule (no path) to a
     * country code or a named generic ending, so these bypasses must keep
     * failing under the narrower rule too.
     */
    public function test_the_url_guard_catches_shape_based_bypasses(): void
    {
        $this->assertSame('Track at', trim(UniSms::stripUrls('Track at bit.ly/xyz123')));
        $this->assertSame('Shop at', trim(UniSms::stripUrls('Shop at EXAMPLE.SHOP')));
        $this->assertSame('Track at', trim(UniSms::stripUrls('Track at https://Bit.Ly/abc')));
        $this->assertSame('Visit today', UniSms::stripUrls('Visit example.com today'));

        $stripped = UniSms::stripUrls('Track at newgearday.com.ph/track/ABC123 now');
        $this->assertSame('Track at now', $stripped);
        $this->assertStringNotContainsString('.ph', $stripped, 'No leftover path fragment.');
        $this->assertStringNotContainsString('/track', $stripped, 'No leftover path fragment.');

        $this->assertSame('Reply to for help', UniSms::stripUrls('Reply to happy@newgearday.com for help'));

        $this->assertSame('See', trim(UniSms::stripUrls('See (bit.ly/abc)')));
        $this->assertSame('See', trim(UniSms::stripUrls('See bit.ly/abc,')));
    }

    /**
     * A long template filled with long placeholder values must still be
     * capped at UniSms::MAX_LENGTH after substitution and URL stripping, not
     * only when the raw template happens to be short.
     */
    public function test_a_long_composed_message_is_capped_at_max_length(): void
    {
        $template = str_repeat('{store_name} {order_status} {customer_name} {courier_name} ', 20) . 'end';
        config(['app.name' => str_repeat('VentaCart Music Gear Superstore ', 5)]);

        $order = Order::create([
            'store_id' => 1,
            'first_name' => str_repeat('Junevievealondracristina', 5),
            'last_name' => str_repeat('Sillacosdelacruzmontenegro', 5),
            'email' => 'long@example.test',
            'phone' => '09171234567',
            'order_status_id' => $this->order->order_status_id,
        ]);

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose(
            $template, $order,
            str_repeat('AwaitingCourierPickupConfirmation', 5),
            null
        );

        $this->assertLessThanOrEqual(UniSms::MAX_LENGTH, mb_strlen($text));
    }

    /** The page offers a copy chip per placeholder; each one must be one compose() fills. */
    public function test_every_offered_placeholder_is_filled_in(): void
    {
        $every = implode(' ', array_map(fn ($p) => '{' . $p . '}', UniSms::PLACEHOLDERS));
        $template = $every;

        $text = (new \Extensions\SmsUnisms\SmsChannel())->compose($template, $this->order, 'Shipped', 'Packed today');

        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $text);
    }

    public function test_the_page_offers_every_placeholder_to_copy(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $page = $this->get(route('admin.extensions.unisms.index'))->assertOk();

        foreach (UniSms::PLACEHOLDERS as $placeholder) {
            $page->assertSee('{' . $placeholder . '}', false);
        }
    }
}
