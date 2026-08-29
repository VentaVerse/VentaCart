<?php

namespace Tests\Feature;

use App\Models\OrderStatus;
use App\Models\Setting;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The fulfillment module stopped guessing its booked, unbooked and cancel
 * statuses. An existing store that was living on those guesses must not
 * notice: the migration pins exactly what it was effectively using, once,
 * and only where nothing was chosen. A fresh install pins nothing.
 */
class PinFulfillmentStatusDefaultsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require base_path('database/migrations/2026_08_30_130000_pin_fulfillment_status_defaults.php');
    }

    /** InstallState reads the migrations table: batch > 1 means an existing store. */
    private function beAnExistingStore(): void
    {
        DB::table('migrations')->insert(['migration' => '9999_99_99_000000_later_deploy', 'batch' => 2]);
    }

    private function placed(string $placement, string $name, int $sort): OrderStatus
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => $name, 'sort_order' => $sort]);
        \Extensions\CourierFulfillment\PlacementMap::put($status->id, $placement);

        return $status;
    }

    private function setting(string $key): ?string
    {
        return Setting::where('group', 'courier')->where('key', $key)->value('value');
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    public function test_an_existing_store_gets_its_old_guesses_pinned(): void
    {
        $this->beAnExistingStore();
        $this->placed('to_handover', 'Zulu Handover', 1);          // first by sort, but the NAME used to win
        $named = $this->placed('to_handover', 'Order Being Shipped', 9);
        $firstPack = $this->placed('to_pack', 'Processing', 1);
        $this->placed('to_pack', 'Payment Received', 2);
        $canceled = OrderStatus::create(['store_id' => 1, 'name' => 'Canceled', 'sort_order' => 7]);

        $this->migration()->up();

        $this->assertSame((string) $named->id, $this->setting('booked_order_status_id'));
        $this->assertSame((string) $firstPack->id, $this->setting('unbooked_order_status_id'));
        $this->assertSame((string) $canceled->id, $this->setting('cancel_order_status_id'));
    }

    public function test_a_choice_already_made_is_never_touched(): void
    {
        $this->beAnExistingStore();
        $mine = $this->placed('to_handover', 'Handed Over', 5);
        $this->placed('to_handover', 'Order Being Shipped', 1);
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'booked_order_status_id'], ['value' => (string) $mine->id]);

        $this->migration()->up();

        $this->assertSame((string) $mine->id, $this->setting('booked_order_status_id'));
    }

    public function test_a_fresh_install_pins_nothing(): void
    {
        // Everything in batch 1 is what a fresh install looks like (the
        // extension install in setUp opened a second batch; fold it back).
        // Even with statuses placed and a Canceled status seeded, nothing is
        // written; the merchant chooses.
        DB::table('migrations')->update(['batch' => 1]);
        $this->placed('to_handover', 'Order Being Shipped', 1);
        OrderStatus::create(['store_id' => 1, 'name' => 'Canceled', 'sort_order' => 7]);

        $this->migration()->up();

        $this->assertNull($this->setting('booked_order_status_id'));
        $this->assertNull($this->setting('unbooked_order_status_id'));
        $this->assertNull($this->setting('cancel_order_status_id'));
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->beAnExistingStore();
        $this->placed('to_handover', 'Order Being Shipped', 1);

        $this->migration()->up();
        $first = $this->setting('booked_order_status_id');
        $this->migration()->up();

        $this->assertSame($first, $this->setting('booked_order_status_id'));
        $this->assertSame(1, Setting::where('group', 'courier')->where('key', 'booked_order_status_id')->count());
    }
}
