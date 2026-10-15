<?php

namespace Tests\Feature\Notifications;

use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderNotificationService;
use App\Support\NotifyChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner ruling 2026-09-26: core emails a customer about a status change only
 * when someone asks. Which statuses email by themselves, for changes nobody
 * is watching, is kept by the extension that automates order handling
 * (fulfillment's Status Mapping), as it was in OpenCart.
 */
class AutomaticEmailTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatus $shipped;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());
        $this->shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
    }

    private function installFulfillment(): void
    {
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_without_an_automating_extension_nothing_emails_by_itself(): void
    {
        $plan = OrderNotificationService::plan($this->shipped, NotifyChoice::follow());

        $this->assertFalse($plan['email']);
    }

    public function test_someone_asking_still_emails_without_one(): void
    {
        $plan = OrderNotificationService::plan($this->shipped, NotifyChoice::explicit(true));

        $this->assertTrue($plan['email']);
    }

    public function test_fulfillments_status_mapping_list_decides_the_automatic_email(): void
    {
        $this->installFulfillment();
        $packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 2]);
        \Extensions\CourierFulfillment\NotifyMap::put([$this->shipped->id]);

        $this->assertTrue(OrderNotificationService::plan($this->shipped, NotifyChoice::follow())['email']);
        $this->assertFalse(OrderNotificationService::plan($packing, NotifyChoice::follow())['email']);
    }

    public function test_the_status_mapping_page_saves_the_email_list(): void
    {
        $this->installFulfillment();

        $this->post(route('admin.fulfillment.settings.placement.update'), [
            'placements' => [$this->shipped->id => 'shipping'],
            'notify' => [$this->shipped->id => '1'],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$this->shipped->id], \Extensions\CourierFulfillment\NotifyMap::all());
    }

    public function test_the_order_statuses_page_offers_no_email_toggle(): void
    {
        $this->get(route('admin.order-statuses.index'))
            ->assertOk()
            ->assertDontSee('emails_customer', false)
            ->assertDontSee('Emails customer');
    }
}
