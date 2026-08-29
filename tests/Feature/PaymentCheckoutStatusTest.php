<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentManager;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatuslessTestPayment implements PaymentMethodInterface
{
    public function getKey(): string { return 'statusless'; }
    public function getBaseKey(): string { return 'statusless'; }
    public function getName(): string { return 'Statusless Pay'; }
    public function getDescription(): string { return ''; }
    public function getImage(): ?string { return null; }
    public function isEnabled(): bool { return true; }
    public function processPayment(Order $order): PaymentResult { return new PaymentResult(true); }
}

/**
 * A payment method's "Order status on checkout" lists the store's statuses
 * and nothing else: no Default (Pending) option, no Not selected option
 * (owner ruling 2026-08-30). The merchant picks one on the method's page.
 * An existing store's effective fallback is pinned at upgrade; a fresh
 * install pins nothing and picks.
 */
class PaymentCheckoutStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PaymentManager::reset();
        PaymentManager::register(StatuslessTestPayment::class);
    }

    protected function tearDown(): void
    {
        PaymentManager::reset();
        parent::tearDown();
    }

    private function orderStatus(string $name, bool $default = false): OrderStatus
    {
        return OrderStatus::create(['store_id' => 1, 'name' => $name, 'is_default' => $default, 'sort_order' => 1]);
    }

    public function test_the_cod_and_bank_transfer_pages_list_statuses_and_no_default_or_blank_option(): void
    {
        app(\App\Services\ExtensionManager::class)->install('payment-cod');
        app(\App\Services\ExtensionManager::class)->install('payment-bank-transfer');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->orderStatus('Pending', default: true);
        $this->orderStatus('COD, to confirm');

        foreach (['cod', 'bank_transfer'] as $key) {
            $html = $this->actingAs(User::factory()->create())
                ->get('/' . config('admin.prefix', 'admin') . '/extensions/payments/' . $key)
                ->assertOk()
                ->assertSee('COD, to confirm')
                ->assertDontSee('Default (Pending)')
                ->assertDontSee('Not selected')
                ->getContent();

            $this->assertMatchesRegularExpression('/name="settings\[' . $key . '_success_status_id\]"[^>]*>\s*<option value="\d+"/', $html, 'The first option is a real status, not a blank.');
        }
    }

    public function test_the_chosen_status_is_used_and_a_deleted_one_reads_as_unset(): void
    {
        $this->orderStatus('Pending', default: true);
        $chosen = $this->orderStatus('COD, to confirm');
        Setting::create(['group' => 'payment', 'key' => 'statusless_success_status_id', 'value' => (string) $chosen->id]);

        $this->assertSame($chosen->id, PaymentManager::checkoutStatus(new StatuslessTestPayment())?->id);

        $chosen->delete();

        $this->assertNull(PaymentManager::checkoutStatus(new StatuslessTestPayment()));
        // Still enabled: the extension switch is the only on/off.
        $this->assertSame(['statusless'], array_map(fn ($m) => $m->getKey(), PaymentManager::enabledMethods()));
    }

    public function test_the_migration_pins_the_default_an_existing_store_was_living_on(): void
    {
        $default = $this->orderStatus('Pending', default: true);
        $chosen = $this->orderStatus('Bank, awaiting payment');
        Setting::create(['group' => 'payment', 'key' => 'bank_transfer_success_status_id', 'value' => (string) $chosen->id]);
        DB::table('migrations')->insert(['migration' => '9999_99_99_000000_later_deploy', 'batch' => 2]); // an existing store

        (require base_path('database/migrations/2026_08_30_150000_pin_payment_checkout_statuses.php'))->up();

        $this->assertSame((string) $default->id, Setting::where('group', 'payment')->where('key', 'cod_success_status_id')->value('value'), 'COD had nothing chosen: pinned to what it was using.');
        $this->assertSame((string) $chosen->id, Setting::where('group', 'payment')->where('key', 'bank_transfer_success_status_id')->value('value'), 'A choice already made is untouched.');
    }

    public function test_the_migration_pins_nothing_on_a_fresh_install(): void
    {
        $this->orderStatus('Pending', default: true);
        DB::table('migrations')->update(['batch' => 1]);

        (require base_path('database/migrations/2026_08_30_150000_pin_payment_checkout_statuses.php'))->up();

        $this->assertNull(Setting::where('group', 'payment')->where('key', 'cod_success_status_id')->value('value'));
    }
}
