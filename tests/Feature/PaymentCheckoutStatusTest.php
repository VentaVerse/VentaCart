<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PaymentManager;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use Database\Seeders\ExtensionSeeder;
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
 * A payment method's "Order status on checkout" is the only status an
 * order paid by it can be born with (owner ruling 2026-08-30). There is no
 * fallback to the store's Default status any more: a method with none
 * selected, or whose status was deleted, is not offered at checkout.
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

    public function test_a_method_with_no_status_selected_is_not_offered(): void
    {
        $this->orderStatus('Pending', default: true); // the old fallback would have used this

        $this->assertSame([], array_map(fn ($m) => $m->getKey(), PaymentManager::offerableMethods()));
        $this->assertSame([], array_map(fn ($m) => $m->getKey(), app(CheckoutService::class)->getAvailablePaymentMethods()));
    }

    public function test_a_method_with_a_status_is_offered_and_loses_it_when_the_status_goes(): void
    {
        $this->orderStatus('Pending', default: true);
        $chosen = $this->orderStatus('COD, to confirm');
        Setting::create(['group' => 'payment', 'key' => 'statusless_success_status_id', 'value' => (string) $chosen->id]);

        $this->assertSame(['statusless'], array_map(fn ($m) => $m->getKey(), PaymentManager::offerableMethods()));
        $this->assertSame($chosen->id, PaymentManager::checkoutStatus(new StatuslessTestPayment())?->id);

        $chosen->delete();

        $this->assertNull(PaymentManager::checkoutStatus(new StatuslessTestPayment()), 'No fallback to the Default status.');
        $this->assertSame([], PaymentManager::offerableMethods());
    }

    public function test_the_cod_and_bank_transfer_pages_offer_not_selected_and_no_named_default(): void
    {
        app(\App\Services\ExtensionManager::class)->install('payment-cod');
        app(\App\Services\ExtensionManager::class)->install('payment-bank-transfer');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->orderStatus('Pending', default: true);

        foreach (['cod', 'bank_transfer'] as $key) {
            $this->actingAs(User::factory()->create())
                ->get('/' . config('admin.prefix', 'admin') . '/extensions/payments/' . $key)
                ->assertOk()
                ->assertSee('Not selected')
                ->assertDontSee('Default (Pending)');
        }
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

    public function test_the_payments_page_names_an_enabled_method_that_has_no_status(): void
    {
        app(\App\Services\ExtensionManager::class)->install('payment-cod');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->orderStatus('Pending', default: true);
        $url = '/' . config('admin.prefix', 'admin') . '/extensions/payments';

        $this->actingAs(User::factory()->create())->get($url)->assertOk()->assertSee('No order status selected');

        Setting::create(['group' => 'payment', 'key' => 'cod_success_status_id', 'value' => (string) $this->orderStatus('COD, to confirm')->id]);

        $this->get($url)->assertOk()->assertDontSee('No order status selected');
    }
}
