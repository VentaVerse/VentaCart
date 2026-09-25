<?php

namespace Tests\Feature\Notifications;

use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Does this status tell the customer" is a fact about the status, kept
 * beside the other facts a status already carries.
 */
class StatusEmailTickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());
    }

    public function test_a_status_starts_silent(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);

        $this->assertFalse($status->emails_customer, 'A new status must not email anyone until the store says so.');
    }

    public function test_the_tick_is_saved_from_the_order_statuses_screen(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);

        $this->put(route('admin.order-statuses.update', $status), [
            'name' => 'Shipped',
            'sort_order' => 2,
            'emails_customer' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($status->fresh()->emails_customer);
    }
}
