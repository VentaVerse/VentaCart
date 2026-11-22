<?php

namespace Tests\Feature\Catalog;

use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductSpecial;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product form's Specials & Discounts tab: a timed special price for
 * every buyer, and always-on discounts by customer group and quantity.
 */
class ProductSpecialsDiscountsFormTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroup $retail;

    private CustomerGroup $resellers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        $this->retail = CustomerGroup::create(['store_id' => 1, 'name' => 'Default', 'is_default' => true]);
        $this->resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);

        $this->actingAs(User::factory()->create());
    }

    private function product(): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => 'Demo Kettle', 'slug' => 'demo-kettle', 'sku' => 'DEMO-001',
            'price' => 1000, 'quantity' => 10, 'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function save(Product $product, array $extra): \Illuminate\Testing\TestResponse
    {
        return $this->put(route('admin.products.update', $product), [
            'store_id' => 1, 'name' => 'Demo Kettle', 'price' => 1000, 'is_active' => '1',
        ] + $extra);
    }

    private function special(array $overrides = []): array
    {
        return $overrides + [
            'price_type' => 'percent', 'value' => '20',
            'date_start' => '', 'time_start' => '', 'date_end' => '2026-12-31', 'time_end' => '',
        ];
    }

    private function discount(array $overrides = []): array
    {
        return $overrides + [
            'customer_group_id' => '', 'quantity' => '1', 'price_type' => 'fixed', 'value' => '900',
        ];
    }

    public function test_saves_specials_and_discounts(): void
    {
        $product = $this->product();

        $this->save($product, [
            'specials' => [
                $this->special(['price_type' => 'fixed', 'value' => '800', 'date_start' => '2026-12-01', 'time_start' => '18:00']),
            ],
            'discounts' => [
                $this->discount(['customer_group_id' => (string) $this->resellers->id, 'quantity' => '10', 'price_type' => 'percent', 'value' => '15']),
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $special = ProductSpecial::where('product_id', $product->id)->sole();
        $this->assertSame('fixed', $special->price_type);
        $this->assertSame('800.00', $special->value);
        $this->assertSame('2026-12-01 18:00:00', $special->date_start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-31 23:59:59', $special->date_end->format('Y-m-d H:i:s'));

        $discount = ProductDiscount::where('product_id', $product->id)->sole();
        $this->assertSame($this->resellers->id, (int) $discount->customer_group_id);
        $this->assertSame(10, $discount->quantity);
        $this->assertSame('percent', $discount->price_type);
        $this->assertSame('15.00', $discount->value);

        // Saving again replaces both lists rather than adding to them.
        $this->save($product, [
            'specials' => [$this->special()],
            'discounts' => [],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, ProductSpecial::where('product_id', $product->id)->count());
        $this->assertSame(0, ProductDiscount::where('product_id', $product->id)->count());
    }

    public function test_special_requires_end_date(): void
    {
        $product = $this->product();

        $this->save($product, ['specials' => [$this->special(['date_end' => ''])]])
            ->assertSessionHasErrors(['specials.0.date_end' => 'Special price row 1: choose when it ends.']);

        $this->assertSame(0, ProductSpecial::where('product_id', $product->id)->count());
    }

    public function test_percent_must_be_below_100(): void
    {
        $product = $this->product();

        $this->save($product, ['specials' => [$this->special(['value' => '100'])]])
            ->assertSessionHasErrors('specials.0.value');
        $this->save($product, ['specials' => [$this->special(['value' => '0'])]])
            ->assertSessionHasErrors('specials.0.value');
        $this->save($product, ['discounts' => [$this->discount(['price_type' => 'percent', 'value' => '120'])]])
            ->assertSessionHasErrors('discounts.0.value');

        // A fixed price may be any amount from zero up.
        $this->save($product, ['discounts' => [$this->discount(['value' => '0'])]])
            ->assertSessionHasNoErrors();
        $this->save($product, ['discounts' => [$this->discount(['value' => '-1'])]])
            ->assertSessionHasErrors('discounts.0.value');

        $this->save($product, ['specials' => [$this->special(['value' => '99.5'])]])
            ->assertSessionHasNoErrors();
        $this->assertSame('99.50', ProductSpecial::where('product_id', $product->id)->sole()->value);
    }

    public function test_duplicate_discount_group_and_quantity_refused(): void
    {
        $product = $this->product();
        $group = (string) $this->resellers->id;

        $this->save($product, ['discounts' => [
            $this->discount(['customer_group_id' => $group, 'quantity' => '5']),
            $this->discount(['customer_group_id' => $group, 'quantity' => '5', 'value' => '850']),
        ]])->assertSessionHasErrors([
            'discounts.1.quantity' => 'Discount row 2: another row already sets this group and quantity.',
        ]);
        $this->assertSame(0, ProductDiscount::where('product_id', $product->id)->count());

        // The same quantity for another group, or for all groups, is fine.
        $this->save($product, ['discounts' => [
            $this->discount(['customer_group_id' => $group, 'quantity' => '5']),
            $this->discount(['customer_group_id' => (string) $this->retail->id, 'quantity' => '5']),
            $this->discount(['customer_group_id' => '', 'quantity' => '5']),
        ]])->assertSessionHasNoErrors();
        $this->assertSame(3, ProductDiscount::where('product_id', $product->id)->count());

        // Two all-groups rows at one quantity clash too.
        $this->save($product, ['discounts' => [
            $this->discount(['quantity' => '2']),
            $this->discount(['quantity' => '2']),
        ]])->assertSessionHasErrors('discounts.1.quantity');

        $this->save($product, ['discounts' => [$this->discount(['quantity' => '0'])]])
            ->assertSessionHasErrors('discounts.0.quantity');
        $this->save($product, ['discounts' => [$this->discount(['customer_group_id' => '9999'])]])
            ->assertSessionHasErrors('discounts.0.customer_group_id');
    }

    public function test_all_groups_discount_saved_as_null_group(): void
    {
        $product = $this->product();

        $this->save($product, ['discounts' => [$this->discount(['customer_group_id' => ''])]])
            ->assertSessionHasNoErrors();

        $discount = ProductDiscount::where('product_id', $product->id)->sole();
        $this->assertNull($discount->customer_group_id);
        $this->assertSame(1, $discount->quantity);
    }

    public function test_edit_page_shows_specials_and_discounts_tab(): void
    {
        $product = $this->product();
        ProductSpecial::create([
            'product_id' => $product->id, 'price_type' => 'percent', 'value' => 20,
            'date_start' => null, 'date_end' => '2026-12-31',
        ]);
        ProductDiscount::create([
            'product_id' => $product->id, 'customer_group_id' => $this->resellers->id,
            'quantity' => 1, 'price_type' => 'fixed', 'value' => 900,
        ]);
        ProductDiscount::create([
            'product_id' => $product->id, 'customer_group_id' => null,
            'quantity' => 5, 'price_type' => 'percent', 'value' => 5,
        ]);

        $html = $this->get(route('admin.products.edit', $product))->assertOk()->getContent();

        $this->assertStringContainsString('Specials &amp; Discounts', $html);
        $this->assertStringContainsString('id="panel-pricing"', $html);
        $this->assertStringContainsString('Special price', $html);
        $this->assertStringContainsString('What buyers pay now', $html);
        $this->assertStringContainsString('All customer groups', $html);
        $this->assertStringNotContainsString('Special pricing', $html);

        // The rows as the form edits them.
        $this->assertStringContainsString('"price_type":"percent","value":"20.00"', $html);
        $this->assertStringContainsString('"date_end":"2026-12-31","time_end":""', $html);
        $this->assertStringContainsString('"customer_group_id":"' . $this->resellers->id . '","quantity":1,"price_type":"fixed","value":"900.00"', $html);
        $this->assertStringContainsString('"customer_group_id":"","quantity":5', $html);

        // The groups the summary and the dropdown list.
        $this->assertStringContainsString('{"id":"' . $this->resellers->id . '","name":"Resellers"}', $html);
    }
}
