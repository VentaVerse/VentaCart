<?php

namespace Tests\Feature;

use App\Mail\TemplateMail;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\ShippingCourier;
use App\Services\ExtensionManager;
use App\Services\OrderNotificationService;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ShippingFakeCourier;
use Tests\TestCase;

/**
 * What the shopper is told when an order moves, and whether they can click it.
 *
 * Four paths sent these emails and each resolved tracking its own way or not
 * at all: the status email never carried tracking, the courier-webhook path
 * could not link the number it had, and a recorded manual shipment with
 * "notify customer" ticked sent nothing (OrderStatusChanger never sends mail,
 * and that path called only it). Every path now goes through
 * OrderNotificationService, which is what these tests pin.
 */
class OrderTrackingEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        if (class_exists(\Extensions\CourierFulfillment\CourierManager::class, false)) {
            $ref = new \ReflectionProperty(\Extensions\CourierFulfillment\CourierManager::class, 'registered');
            $ref->setAccessible(true);
            $registered = $ref->getValue();
            unset($registered['fakeship'], $registered['fake']);
            $ref->setValue(null, $registered);
        }
        parent::tearDown();
    }

    // ------------------------------------------------------------ fixtures

    private function courier(): ShippingCourier
    {
        return ShippingCourier::create([
            'store_id'     => 1,
            'name'         => 'LBC Express',
            'tracking_url' => 'https://lbc.test/track/{tracking_number}',
            'is_active'    => true,
            'sort_order'   => 1,
        ]);
    }

    private function orderStatus(string $name, int $sort = 1): OrderStatus
    {
        return OrderStatus::firstOrCreate(['store_id' => 1, 'name' => $name], ['sort_order' => $sort]);
    }

    private function order(?OrderStatus $status = null): Order
    {
        return Order::create([
            'store_id'            => 1,
            'order_status_id'     => ($status ?? $this->orderStatus('Processing'))->id,
            'first_name'          => 'June',
            'last_name'           => 'Sillacos',
            'email'               => 'june@example.com',
            'phone'               => '09171234567',
            'shipping_first_name' => 'June',
            'shipping_last_name'  => 'Sillacos',
            'shipping_address_1'  => '123 Mango St',
            'shipping_barangay'   => 'Lahug',
            'shipping_city'       => 'Cebu City',
            'shipping_state'      => 'Cebu',
            'shipping_postcode'   => '6000',
            'shipping_phone'      => '09171234567',
            'payment_method'      => 'bank_transfer',
            'subtotal'            => 1000,
            'shipping_cost'       => 100,
            'total'               => 1100,
        ]);
    }

    private function installFulfillment(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function placed(string $placement, string $name, int $sort = 1): OrderStatus
    {
        $status = $this->orderStatus($name, $sort);
        \Extensions\CourierFulfillment\PlacementMap::put($status->id, $placement);

        return $status;
    }

    private function sentBody(): string
    {
        $bodies = [];
        Mail::assertSent(TemplateMail::class, function (TemplateMail $m) use (&$bodies) {
            $bodies[] = $m->renderedBody;

            return true;
        });

        return implode("\n----\n", $bodies);
    }

    // ------------------------------------------------- the status email

    public function test_the_status_email_names_the_courier_and_links_the_number(): void
    {
        $order = $this->order();
        $order->update(['shipping_courier_id' => $this->courier()->id, 'tracking_number' => 'LBC123']);

        $this->assertTrue(OrderNotificationService::statusChanged($order->fresh(), 'Processing', 'Shipped', ''));

        $body = $this->sentBody();
        $this->assertStringContainsString('LBC Express', $body);
        $this->assertStringContainsString('href="https://lbc.test/track/LBC123"', $body);
        $this->assertStringContainsString('>LBC123<', $body);
        $this->assertStringNotContainsString('{tracking_details}', $body);
    }

    public function test_the_status_email_prints_no_tracking_block_when_there_is_none(): void
    {
        $order = $this->order();

        $this->assertTrue(OrderNotificationService::statusChanged($order, 'Pending', 'Processing', 'Payment received.'));

        $body = $this->sentBody();
        $this->assertStringContainsString('Payment received.', $body);
        $this->assertStringNotContainsString('Tracking number', $body);
        $this->assertStringNotContainsString('{tracking_details}', $body);
        $this->assertStringNotContainsString('{carrier_name}', $body);
    }

    public function test_the_shipping_email_is_not_sent_without_a_number(): void
    {
        $this->assertFalse(OrderNotificationService::shipped($this->order()));
        Mail::assertNothingSent();
    }

    public function test_the_seeded_status_template_carries_the_tracking_block(): void
    {
        $template = \App\Models\EmailTemplate::where('slug', 'order_status_update')->firstOrFail();

        $this->assertStringContainsString('{tracking_details}', $template->body);
        $this->assertStringNotContainsString("\u{2014}", $template->subject, 'No em dashes in UI strings.');
    }

    // ------------------------------------------- the courier webhook path

    public function test_a_courier_status_move_emails_the_tracking_it_already_holds(): void
    {
        $this->installFulfillment();
        $delivered = $this->orderStatus('Delivered', 5);
        Setting::create(['group' => 'courier', 'key' => 'fake_status_map', 'value' => json_encode([
            'delivered' => ['order_status_id' => $delivered->id, 'notify' => true],
        ])]);

        $order = $this->order();
        $order->forceFill([
            'courier_provider'      => 'fake',
            'tracking_number'       => 'FK-77',
            'tracking_link'         => 'https://fake.test/FK-77',
            'shipping_courier_name' => 'Fake Courier',
        ])->save();

        $this->assertTrue(\Extensions\CourierFulfillment\StatusMapper::apply($order, 'delivered'));

        $body = $this->sentBody();
        $this->assertStringContainsString('Fake Courier', $body);
        $this->assertStringContainsString('href="https://fake.test/FK-77"', $body);
    }

    // ------------------------------------------ the recorded shipment path

    public function test_a_recorded_shipment_with_notify_sends_the_shipping_email(): void
    {
        $this->installFulfillment();
        $this->placed('to_handover', 'Order Being Shipped', 3);
        $order = $this->order($this->placed('to_pack', 'Processing', 2));
        $courier = $this->courier();

        $result = (new \Extensions\CourierFulfillment\FulfillmentService())->bookManual($order, [
            'shipping_courier_id' => $courier->id,
            'tracking_number'     => 'LBC999',
            'notify_customer'     => true,
        ]);
        $this->assertTrue($result['success'], $result['error'] ?? '');

        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedSubject, 'shipped')
            && str_contains($m->renderedBody, 'href="https://lbc.test/track/LBC999"')
            && str_contains($m->renderedBody, 'LBC Express'));
    }

    public function test_a_recorded_shipment_without_a_number_still_tells_the_customer_it_moved(): void
    {
        $this->installFulfillment();
        $this->placed('to_handover', 'Order Being Shipped', 3);
        $order = $this->order($this->placed('to_pack', 'Processing', 2));
        $courier = $this->courier();

        $result = (new \Extensions\CourierFulfillment\FulfillmentService())->bookManual($order, [
            'shipping_courier_id' => $courier->id,
            'notify_customer'     => true,
        ]);
        $this->assertTrue($result['success'], $result['error'] ?? '');

        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => str_contains($m->renderedBody, 'Order Being Shipped'));
    }

    public function test_a_recorded_shipment_without_notify_sends_nothing(): void
    {
        $this->installFulfillment();
        $this->placed('to_handover', 'Order Being Shipped', 3);
        $order = $this->order($this->placed('to_pack', 'Processing', 2));

        (new \Extensions\CourierFulfillment\FulfillmentService())->bookManual($order, [
            'shipping_courier_id' => $this->courier()->id,
            'tracking_number'     => 'LBC1',
            'notify_customer'     => false,
        ]);

        Mail::assertNothingSent();
    }

    // -------------------------------------- the integrated booking's name

    public function test_an_integrated_booking_stamps_the_couriers_name_and_cancelling_clears_it(): void
    {
        $this->installFulfillment();
        \Extensions\CourierFulfillment\CourierManager::register(ShippingFakeCourier::class);
        $this->placed('to_handover', 'Order Being Shipped', 3);
        $order = $this->order($this->placed('to_pack', 'Processing', 2));

        $result = (new \Extensions\CourierFulfillment\FulfillmentService())->book($order, ['courier' => 'fakeship']);
        $this->assertTrue($result['success'], $result['error'] ?? '');

        $order = $order->fresh();
        $this->assertSame('Fake Ship', $order->shipping_courier_name, 'The shopper would see a bare number with no courier beside it.');
        $this->assertSame('FS-0001', $order->tracking_number);

        \Extensions\CourierFulfillment\FulfillmentService::cancelBooking($order);

        $this->assertNull($order->fresh()->shipping_courier_name);
        $this->assertNull($order->fresh()->tracking_number);
    }
}
