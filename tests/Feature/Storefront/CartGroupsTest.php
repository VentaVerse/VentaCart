<?php

namespace Tests\Feature\Storefront;

use App\Contracts\CartLineGrouper;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Product;
use App\Support\CartGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;

/**
 * An extension may group the cart's lines (one group per seller); core
 * checks the groups before any theme draws them.
 */
class CartGroupsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;

    private function product(string $sku, string $name): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => $name, 'slug' => strtolower($sku), 'sku' => $sku,
            'price' => 500, 'quantity' => 20, 'is_active' => true,
        ]);
    }

    /** @return list<int> the cart line ids, in cart order */
    private function fillCart(): array
    {
        $customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test', 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($customer, 'customer');
        $cart = Cart::create(['store_id' => 1, 'customer_id' => $customer->id]);

        $ids = [];
        foreach ([$this->product('DEMO-001', 'Demo mug'), $this->product('OTHER-1', 'Plain mug'), $this->product('DEMO-002', 'Demo cup')] as $product) {
            $ids[] = (int) $cart->items()->create([
                'product_id' => $product->id, 'name' => $product->name, 'price' => 500, 'quantity' => 1,
            ])->id;
        }

        return $ids;
    }

    public function test_without_a_grouper_the_mini_cart_has_no_groups(): void
    {
        $this->fillCart();

        $this->getJson(route('cart.mini'))->assertOk()->assertJsonPath('groups', null);
        $this->assertNull(CartGroups::for(collect()));
    }

    public function test_the_fixture_extensions_groups_reach_the_mini_cart_and_the_cart_page(): void
    {
        $this->installSampleExtension();
        [$mug, $plain, $cup] = $this->fillCart();

        $this->getJson(route('cart.mini'))->assertOk()->assertJsonPath('groups', [
            [
                'key' => 'demo-store', 'label' => 'Demo Store', 'url' => 'https://example.com/shops/demo-store',
                'lines' => [$mug, $cup],
                // JSON writes 120.0 as 120.
                'shipping' => ['label' => 'Shipping from this shop', 'amount' => 120, 'note' => 'Ships in 2 days'],
            ],
            ['key' => 'other', 'label' => '', 'url' => null, 'lines' => [$plain], 'shipping' => null],
        ]);

        $this->get(route('cart.index'))->assertOk()->assertViewHas('cartGroups', fn ($groups) => count($groups) === 2);
    }

    public function test_switching_the_extension_off_gives_the_plain_cart(): void
    {
        $this->installSampleExtension();
        $this->fillCart();
        $this->switchOffSampleExtension();

        $this->getJson(route('cart.mini'))->assertJsonPath('groups', null);
    }

    public function test_the_answer_is_checked(): void
    {
        $groups = CartGroups::clean([
            ['key' => 'shop-1', 'label' => "Demo\x00 Store", 'url' => 'javascript:alert(1)', 'lines' => [3, '1', 99, 1],
                'shipping' => ['label' => 'Ships', 'amount' => -5, 'note' => str_repeat('n', 300)]],
            ['key' => 'shop-1', 'label' => 'Same key twice', 'lines' => [2]],
            ['key' => 'Bad Key', 'label' => 'Bad', 'lines' => [2]],
            ['key' => 'other', 'label' => 'Takes the reserved key', 'lines' => [2]],
            ['key' => 'shop-2', 'label' => 'Already placed', 'lines' => [1, 3]],
            'not a group',
        ], [1, 2, 3]);

        $this->assertSame([
            ['key' => 'shop-1', 'label' => 'Demo  Store', 'url' => null, 'lines' => [1, 3],
                'shipping' => ['label' => 'Ships', 'amount' => null, 'note' => str_repeat('n', 160)]],
            ['key' => 'other', 'label' => '', 'url' => null, 'lines' => [2], 'shipping' => null],
        ], $groups);
    }

    public function test_at_most_fifty_groups_the_rest_collected(): void
    {
        $answer = array_map(fn (int $i) => ['key' => "shop-{$i}", 'label' => "Shop {$i}", 'lines' => [$i]], range(1, 60));

        $groups = CartGroups::clean($answer, range(1, 60));

        $this->assertCount(CartGroups::MAX_GROUPS, $groups);
        $this->assertSame('other', end($groups)['key']);
        $this->assertSame(range(50, 60), end($groups)['lines']);
    }

    public function test_a_failing_grouper_gives_the_plain_cart_and_is_reported(): void
    {
        CartGroups::use(FailingGrouper::class);
        $reported = [];
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (\Throwable $e) use (&$reported) {
            $reported[] = $e->getMessage();
        });

        $this->assertNull(CartGroups::for(collect([(object) ['id' => 1]])));
        $this->assertSame(['grouper broke'], $reported);
    }

    public function test_a_class_that_is_not_a_grouper_gives_the_plain_cart(): void
    {
        Log::spy();
        CartGroups::use(\stdClass::class);

        $this->assertNull(CartGroups::for(collect([(object) ['id' => 1]])));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_second_grouper_is_refused_and_logged(): void
    {
        Log::spy();
        CartGroups::use(FailingGrouper::class);
        CartGroups::use(FailingGrouper::class);
        CartGroups::use(\Tests\Fixtures\Extensions\SampleExtension\Cart\SampleCartGrouper::class);

        Log::shouldHaveReceived('warning')->once();
        $this->assertSame(FailingGrouper::class, CartGroups::grouper());
    }
}

class FailingGrouper implements CartLineGrouper
{
    public function group(Collection $items): array
    {
        throw new \RuntimeException('grouper broke');
    }
}
