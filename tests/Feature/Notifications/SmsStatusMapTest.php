<?php

namespace Tests\Feature\Notifications;

use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use Extensions\SmsUnisms\SmsChannel;
use Extensions\SmsUnisms\SmsStatusMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsStatusMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('sms-unisms');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_nothing_texts_until_a_status_is_ticked(): void
    {
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);

        $this->assertFalse(SmsStatusMap::texts($shipped->id));
        $this->assertFalse((new SmsChannel())->wantsStatus($shipped->id));
    }

    public function test_a_ticked_status_texts(): void
    {
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
        SmsStatusMap::put([$shipped->id]);

        $this->assertTrue(SmsStatusMap::texts($shipped->id));
        $this->assertTrue((new SmsChannel())->wantsStatus($shipped->id));
    }

    public function test_an_unknown_status_never_texts(): void
    {
        $this->assertFalse(SmsStatusMap::texts(null));
    }

    /**
     * The settings panel is how the store actually ticks these, so the save
     * path needs its own coverage, not just SmsStatusMap::put() called directly.
     */
    public function test_saving_the_panel_stores_the_ticked_ids(): void
    {
        $this->actingAs(User::factory()->create());

        $packing = OrderStatus::create(['store_id' => 1, 'name' => 'Packing', 'sort_order' => 1]);
        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 2]);

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
            'status_ids' => [$shipped->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$shipped->id], SmsStatusMap::all());
        $this->assertFalse(SmsStatusMap::texts($packing->id));
        $this->assertTrue(SmsStatusMap::texts($shipped->id));
    }

    public function test_saving_the_panel_with_nothing_ticked_stores_none(): void
    {
        $this->actingAs(User::factory()->create());

        $shipped = OrderStatus::create(['store_id' => 1, 'name' => 'Shipped', 'sort_order' => 1]);
        SmsStatusMap::put([$shipped->id]);

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
        ])->assertSessionHasNoErrors();

        $this->assertSame([], SmsStatusMap::all());
    }

    public function test_an_unknown_status_id_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.extensions.unisms.update'), [
            'sender_id' => 'TEST',
            'status_ids' => [999999],
        ])->assertSessionHasErrors('status_ids.0');

        $this->assertSame([], SmsStatusMap::all());
    }
}
