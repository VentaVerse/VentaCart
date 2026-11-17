<?php

namespace Tests\Feature\Extensions;

use App\Models\Customer;
use App\Models\Store;
use App\Services\ExtensionManager;
use App\Support\AccountMenu;
use Extensions\StoreCredit\Models\CustomerTransaction;
use Extensions\StoreCredit\StoreCreditHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The customer's store credit page: their balance and their own ledger,
 * newest first, a page at a time, with the hooks other extensions use to
 * name entries and fold runs of them into one line.
 */
class StoreCreditAccountTest extends TestCase
{
    use RefreshDatabase;

    private Customer $juan;

    private Customer $maria;

    protected function setUp(): void
    {
        parent::setUp();
        $store = Store::create(['name' => 'Demo Store', 'code' => 'demo']);
        $this->juan = Customer::create([
            'store_id' => $store->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test', 'password' => bcrypt('secret'),
        ]);
        $this->maria = Customer::create([
            'store_id' => $store->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.test', 'password' => bcrypt('secret'),
        ]);
        $this->beforeApplicationDestroyed(function () {
            if (class_exists(StoreCreditHistory::class, false)) {
                StoreCreditHistory::forget();
            }
        });
    }

    private function switchOn(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install('store-credit');
        $manager->bootEnabled();
        StoreCreditHistory::forget();
    }

    /** A ledger entry, $minutes after a fixed start, so the order is certain. */
    private function entry(Customer $customer, float $amount, string $description, int $minutes): CustomerTransaction
    {
        return CustomerTransaction::create([
            'customer_id' => $customer->id, 'amount' => $amount, 'description' => $description,
            'created_at' => Carbon::parse('2026-01-01 09:00:00')->addMinutes($minutes),
        ]);
    }

    private function open(?Customer $as = null, int $page = 1): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->juan, 'customer')
            ->get(route('store-credit.account', $page > 1 ? ['page' => $page] : []))
            ->assertOk();
    }

    /** @return list<string> the descriptions on the page, top to bottom */
    private function lines(string $html): array
    {
        preg_match_all('#<td data-label="Description">(.*?)</td>#s', $html, $m);

        return array_map(fn (string $text) => html_entity_decode(trim($text)), $m[1]);
    }

    public function test_the_page_shows_the_balance_and_the_customers_own_entries_newest_first(): void
    {
        $this->switchOn();
        $this->entry($this->juan, 500, 'Welcome credit', 1);
        $this->entry($this->juan, -120.5, 'Spent on an order', 2);
        $this->entry($this->maria, 9999, 'Credit for someone else', 3);

        $html = $this->open()
            ->assertSee(store_money(379.5))
            ->assertDontSee('Credit for someone else')
            ->assertDontSee(store_money(9999))
            ->getContent();

        $this->assertSame(['Spent on an order', 'Welcome credit'], $this->lines($html));
        $this->assertStringContainsString('−' . store_money(120.5), $html);
        $this->assertStringContainsString('+' . store_money(500), $html);
        // Balance after each entry: 379.50 after the spend, 500.00 after the welcome credit.
        preg_match_all('#<td data-label="Balance after" class="store-credit__num">(.*?)</td>#s', $html, $m);
        $this->assertSame([store_money(379.5), store_money(500)], array_map('trim', $m[1]));
    }

    public function test_entries_are_printed_as_text(): void
    {
        $this->switchOn();
        $this->entry($this->juan, 10, '<script>alert(1)</script>', 1);

        $this->open()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_the_history_is_paged_and_the_balance_after_holds_across_pages(): void
    {
        $this->switchOn();
        foreach (range(1, 25) as $n) {
            $this->entry($this->juan, 10, "Entry {$n}", $n);
        }

        $first = $this->open()->assertSee('rel="next"', false)->getContent();
        $this->assertSame(array_map(fn ($n) => "Entry {$n}", range(25, 6)), $this->lines($first));

        $second = $this->open(page: 2)->assertSee('rel="prev"', false)->assertDontSee('rel="next"', false)->getContent();
        $this->assertSame(array_map(fn ($n) => "Entry {$n}", range(5, 1)), $this->lines($second));
        preg_match('#<td data-label="Balance after" class="store-credit__num">(.*?)</td>#s', $second, $m);
        $this->assertSame(store_money(50), trim($m[1]), 'Entry 5 left a balance of 50.');

        $this->open(page: 9)->assertSee('Nothing more to show.');
    }

    public function test_an_extension_can_name_entries(): void
    {
        $this->switchOn();
        $order = $this->entry($this->juan, -40, 'order_spend:1042', 1);
        $this->entry($this->juan, 15, 'Goodwill', 2);
        $reported = [];
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (\Throwable $e) use (&$reported) {
            $reported[] = $e->getMessage();
        });

        StoreCreditHistory::describe(fn () => throw new \RuntimeException('describer broke'));
        StoreCreditHistory::describe(fn (CustomerTransaction $entry): ?string => str_starts_with($entry->description, 'order_spend:')
            ? 'Spent on order ' . substr($entry->description, 12) . ' <b>'
            : null);

        $html = $this->open()->getContent();

        $this->assertSame(['Goodwill', 'Spent on order 1042 <b>'], $this->lines($html), 'Named by the extension, else its own description.');
        $this->assertStringNotContainsString(' <b></td>', $html);
        $this->assertContains('describer broke', $reported);
        $this->assertNotNull($order);
    }

    public function test_an_extension_can_fold_consecutive_entries_into_one_line(): void
    {
        $this->switchOn();
        $this->entry($this->juan, 300, 'Welcome credit', 1);
        $this->entry($this->juan, -50, 'Order 7, seller A', 2);
        $this->entry($this->juan, -30, 'Order 7, seller B', 3);
        $this->entry($this->juan, 20, 'Goodwill', 4);
        $this->entry($this->juan, -10, 'Order 7, late fee', 5);

        StoreCreditHistory::group(fn (CustomerTransaction $entry): ?string => str_starts_with($entry->description, 'Order 7') ? 'order-7' : null);

        $html = $this->open()->getContent();

        // The two spends next to each other fold; the later one is apart, so it stays its own line.
        $this->assertSame(['Order 7, late fee', 'Goodwill', 'Order 7, seller A', 'Welcome credit'], $this->lines($html));
        $this->assertStringContainsString('−' . store_money(80), $html, 'The amounts added up.');
        preg_match_all('#<td data-label="Balance after" class="store-credit__num">(.*?)</td>#s', $html, $m);
        $this->assertSame([store_money(230), store_money(240), store_money(220), store_money(300)], array_map('trim', $m[1]), 'The balance after the newest of the fold.');
    }

    public function test_a_fold_is_never_split_across_pages(): void
    {
        $this->switchOn();
        // Oldest first: three entries that fold, then 19 single ones, so the fold is the 20th line.
        foreach (range(1, 3) as $n) {
            $this->entry($this->juan, -1, "Fold {$n}", $n);
        }
        foreach (range(1, 19) as $n) {
            $this->entry($this->juan, 5, "Single {$n}", 10 + $n);
        }
        $this->entry($this->juan, 1, 'Oldest', -10);
        StoreCreditHistory::group(fn (CustomerTransaction $entry): ?string => str_starts_with($entry->description, 'Fold') ? 'fold' : null);

        $first = $this->lines($this->open()->getContent());
        $this->assertCount(20, $first);
        $this->assertSame('Fold 1', end($first), 'The whole fold ends page one, named by its first entry.');
        $this->assertSame(['Oldest'], $this->lines($this->open(page: 2)->getContent()));
    }

    public function test_extensions_register_through_the_event_whenever_they_boot(): void
    {
        $this->switchOn();
        $this->entry($this->juan, 5, 'Plain', 1);
        Event::listen(StoreCreditHistory::EVENT, function (): void {
            StoreCreditHistory::describe(fn (): string => 'Named through the event');
        });

        $this->assertSame(['Named through the event'], $this->lines($this->open()->getContent()));
    }

    public function test_the_account_menu_links_to_the_page_while_the_extension_is_on(): void
    {
        $this->switchOn();

        $links = collect(AccountMenu::links())->keyBy('label');
        $this->assertSame(route('store-credit.account'), $links['Store credit']['url'] ?? null);
        $this->assertSame('wallet', $links['Store credit']['icon']);
    }

    public function test_switched_off_there_is_no_page_and_no_menu_link(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install('store-credit');
        $manager->disable('store-credit');
        app()->forgetInstance(ExtensionManager::class);
        app(ExtensionManager::class)->bootEnabled();

        $this->actingAs($this->juan, 'customer')->get('/account/store-credit')->assertNotFound();
        $this->assertNotContains('Store credit', array_column(AccountMenu::links(), 'label'));
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->switchOn();

        $this->get(route('store-credit.account'))->assertRedirect();
        $this->assertGuest('customer');
    }
}
