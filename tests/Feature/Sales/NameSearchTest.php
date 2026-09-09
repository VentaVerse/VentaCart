<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Store;
use App\Models\User;
use App\Support\NameSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finding a person by the name you would say out loud.
 *
 * The old search looked for the typed words as one unbroken run of
 * characters, across first_name, last_name and the two concatenated. That
 * cannot step over anything in between, so a customer stored as
 * first_name "Paolo Andres", last_name "Reyes" was not found by "Paolo
 * Reyes". On the live table that was 1,227 of 3,547 customers.
 *
 * It is not an artefact of the OpenCart import either: the sign-up form has
 * two boxes, so people type a middle name into the first one. Five of the
 * twenty-nine customers created natively in Venta already had the shape.
 */
class NameSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());
    }

    private function customer(string $first, string $last, string $email = 'x@example.test'): Customer
    {
        return Customer::create([
            'store_id' => 1, 'first_name' => $first, 'last_name' => $last,
            'email' => $email, 'password' => bcrypt('x'), 'is_active' => true,
        ]);
    }

    private function found(string $typed): array
    {
        return Customer::query()
            ->where(fn ($q) => NameSearch::apply($q, $typed, ['email', 'phone']))
            ->pluck('email')->all();
    }

    /** The case that started this: a middle name between the words typed. */
    public function test_a_middle_name_does_not_hide_the_customer(): void
    {
        $this->customer('Paolo Andres', 'Reyes', 'paolo@example.test');

        $this->assertSame(['paolo@example.test'], $this->found('Paolo Reyes'));
    }

    /** The legacy shape, whole name in one column, must keep working. */
    public function test_a_whole_name_in_the_first_name_still_matches(): void
    {
        $this->customer('Paolo Reyes', '', 'legacy@example.test');

        $this->assertSame(['legacy@example.test'], $this->found('Paolo Reyes'));
    }

    /** Word order is how people remember names, not how they are stored. */
    public function test_the_words_may_be_typed_in_either_order(): void
    {
        $this->customer('Maria Clara', 'Santos', 'maria@example.test');

        $this->assertSame(['maria@example.test'], $this->found('Santos Maria'));
    }

    public function test_one_word_still_finds_everyone_who_carries_it(): void
    {
        $this->customer('Juan', 'Dela Cruz', 'juan@example.test');
        $this->customer('Maria', 'Cruz', 'maria@example.test');

        $this->assertEqualsCanonicalizing(
            ['juan@example.test', 'maria@example.test'],
            $this->found('Cruz')
        );
    }

    /** Every word must be present, or the search would widen instead of narrow. */
    public function test_a_word_that_matches_nobody_excludes_the_row(): void
    {
        $this->customer('Juan', 'Dela Cruz', 'juan@example.test');

        $this->assertSame([], $this->found('Juan Santos'));
    }

    /**
     * Email and phone are single tokens with their own punctuation. Splitting
     * them on spaces would be meaningless, and searching "a b" must not match
     * an address merely containing "a".
     */
    public function test_an_email_is_matched_whole_and_never_split(): void
    {
        $this->customer('Zoe', 'Adams', 'zoe.adams@example.test');

        $this->assertSame(['zoe.adams@example.test'], $this->found('zoe.adams@example.test'));
        $this->assertSame([], $this->found('zoe.adams@example.test somethingelse'));
    }

    public function test_extra_spaces_do_not_change_the_result(): void
    {
        $this->customer('Paolo Andres', 'Reyes', 'paolo@example.test');

        $this->assertSame(['paolo@example.test'], $this->found('  Paolo   Reyes  '));
    }

    /** A pasted paragraph must not build an unbounded query. */
    public function test_the_number_of_words_is_capped(): void
    {
        $this->customer('Paolo Andres', 'Reyes', 'paolo@example.test');

        $many = 'Paolo ' . str_repeat('zzz ', 40) . 'Reyes';
        // Whatever it returns, it must not hang or error.
        $this->assertIsArray($this->found($many));
    }

    /** The orders list searches the name copied onto the order. */
    public function test_the_orders_list_finds_an_order_by_full_name(): void
    {
        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'sort_order' => 1]);
        Order::create([
            'store_id' => 1, 'first_name' => 'Paolo Andres', 'last_name' => 'Reyes',
            'email' => 'paolo@example.test', 'order_status_id' => $status->id,
        ]);

        // Assert on something ONLY the matched row carries. The search box
        // echoes the typed term, so asserting "Reyes" passes even when the
        // search finds nothing at all.
        $this->get(route('admin.orders.index', ['search' => 'Paolo Reyes']))
            ->assertOk()
            ->assertSee('paolo@example.test');

        $this->get(route('admin.orders.index', ['search' => 'Paolo Santos']))
            ->assertOk()
            ->assertDontSee('paolo@example.test');
    }

    /** The customers list, through the screen rather than the query. */
    public function test_the_customers_list_finds_a_customer_by_full_name(): void
    {
        $this->customer('Paolo Andres', 'Reyes', 'paolo@example.test');

        $this->get(route('admin.customers.index', ['search' => 'Paolo Reyes']))
            ->assertOk()
            ->assertSee('paolo@example.test');
    }

    /** And the picker the order screens use. */
    public function test_the_order_customer_picker_finds_by_full_name(): void
    {
        $this->customer('Paolo Andres', 'Reyes', 'paolo@example.test');

        $this->getJson(route('admin.orders.search-customers', ['q' => 'Paolo Reyes', 'store_id' => 1]))
            ->assertOk()
            ->assertSee('paolo@example.test');
    }
}
