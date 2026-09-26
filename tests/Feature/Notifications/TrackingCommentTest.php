<?php

namespace Tests\Feature\Notifications;

use App\Mail\TemplateMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\ShippingCourier;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\OrderNotificationService;
use Database\Seeders\EmailTemplateSeeder;
use Extensions\SmsUnisms\SmsChannel;
use Extensions\SmsUnisms\UniSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ResetsNotificationChannels;
use Tests\TestCase;

/**
 * withTracking() used to read tracking_number from trackingPlaceholders(),
 * which is already a finished anchor, then escape it inside a second
 * anchor. The customer saw "&lt;a href=..." in their email and on their
 * account page, and a template using {comment} would have carried the tag
 * towards a handset. It now builds its one anchor from the raw number.
 */
class TrackingCommentTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNotificationChannels;

    private OrderStatus $packing;

    private OrderStatus $shipped;

    private ShippingCourier $courier;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EmailTemplateSeeder::class);
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $this->shipped = OrderStatus::create([
            'store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2, 'emails_customer' => true,
        ]);
        $this->courier = ShippingCourier::create([
            'store_id' => 1, 'name' => 'LBC Express',
            'tracking_url' => 'https://lbc.test/track/{tracking_number}',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $this->customer = Customer::create([
            'store_id' => 1, 'first_name' => 'June', 'last_name' => 'Sillacos',
            'email' => 'june@example.test', 'password' => bcrypt('secret'),
        ]);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        $this->resetNotificationChannels();
        parent::tearDown();
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'store_id' => 1, 'customer_id' => $this->customer->id,
            'first_name' => 'June', 'last_name' => 'Sillacos',
            'email' => 'june@example.test', 'phone' => '+639171234567',
            'order_status_id' => $this->packing->id,
        ], $overrides));
    }

    private function tracked(): Order
    {
        return $this->order([
            'shipping_courier_id' => $this->courier->id,
            'tracking_number' => 'LBC999',
            'tracking_link' => 'https://lbc.test/track/LBC999',
        ]);
    }

    private function assertCleanAnchor(string $html): void
    {
        $this->assertSame(1, substr_count($html, '<a'), 'Exactly one anchor: ' . $html);
        $this->assertStringNotContainsString('&lt;', $html);
        $this->assertStringContainsString('href="https://lbc.test/track/LBC999"', $html);
        $this->assertStringContainsString('>LBC999</a>', $html);
    }

    public function test_with_tracking_builds_one_clean_anchor(): void
    {
        $comment = OrderNotificationService::withTracking($this->tracked(), 'On its way');

        $this->assertCleanAnchor($comment);
        $this->assertStringStartsWith('On its way | LBC Express: <a ', $comment);
    }

    public function test_with_tracking_without_a_link_prints_the_number(): void
    {
        $order = $this->order(['tracking_number' => 'NOLINK1']);

        $this->assertSame('Courier: NOLINK1', OrderNotificationService::withTracking($order));
    }

    public function test_the_order_page_stores_and_emails_a_clean_anchor(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->order();

        $this->post(route('admin.orders.history.store', $order), [
            'order_status_id' => $this->shipped->id,
            'shipping_courier_id' => $this->courier->id,
            'tracking_number' => 'LBC999',
            'comment' => 'On its way',
            'notify_customer' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertCleanAnchor((string) $order->histories()->latest('id')->first()->comment);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedBody, 'LBC999')
            && ! str_contains($m->renderedBody, '&lt;'));
    }

    public function test_the_account_page_shows_the_tracking_as_text(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->order();
        $this->post(route('admin.orders.history.store', $order), [
            'order_status_id' => $this->shipped->id,
            'shipping_courier_id' => $this->courier->id,
            'tracking_number' => 'LBC999',
            'comment' => 'On its way',
            'notify_customer' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('On its way | LBC Express: LBC999')
            ->assertDontSee('&amp;lt;', false)
            ->assertDontSee('&lt;a', false);
    }

    public function test_an_sms_template_with_the_comment_reads_carrier_and_number(): void
    {
        app(ExtensionManager::class)->install('sms-unisms');
        app(ExtensionManager::class)->bootEnabled();
        $order = $this->tracked();

        $text = (new SmsChannel())->compose('{comment}', $order, 'Shipped', OrderNotificationService::withTracking($order));

        $this->assertSame('LBC Express: LBC999', $text);
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringNotContainsString('href', $text);
        $this->assertStringNotContainsString('style', $text);
    }

    public function test_a_recorded_shipment_stores_emails_and_shows_a_clean_anchor(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        foreach (['to_pack' => $this->packing, 'to_handover' => $this->shipped] as $placement => $status) {
            \Extensions\CourierFulfillment\PlacementMap::put($status->id, $placement);
            \Tests\Support\FulfillmentSettings::selectFor($placement, (int) $status->id);
        }
        $order = $this->order();

        $result = (new \Extensions\CourierFulfillment\FulfillmentService())->bookManual($order, [
            'shipping_courier_id' => $this->courier->id,
            'tracking_number' => 'LBC999',
            'order_status_id' => $this->shipped->id,
            'notify_customer' => true,
            'channels' => [],
        ]);
        $this->assertTrue($result['success'], $result['error'] ?? '');

        $history = $order->histories()->latest('id')->first();
        $this->assertCleanAnchor((string) $history->comment);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedBody, 'LBC999')
            && ! str_contains($m->renderedBody, '&lt;'));

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('LBC Express: LBC999')
            ->assertDontSee('&amp;lt;', false);
    }
}
