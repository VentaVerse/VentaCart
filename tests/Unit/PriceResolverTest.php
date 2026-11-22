<?php

namespace Tests\Unit;

use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductSpecial;
use App\Models\Store;
use App\Services\PriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceResolverTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroup $default;

    private CustomerGroup $resellers;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        $this->default = CustomerGroup::create(['store_id' => 1, 'name' => 'Default', 'is_default' => true]);
        $this->resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
    }

    private function product(): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => 'Demo Strap', 'slug' => 'demo-001',
            'sku' => 'DEMO-001', 'price' => 5000, 'is_active' => true,
        ]);
    }

    private function special(Product $p, string $type, float $value, ?string $start = null, ?string $end = '+7 days'): void
    {
        ProductSpecial::create([
            'product_id' => $p->id, 'price_type' => $type, 'value' => $value,
            'date_start' => $start ? now()->modify($start) : null,
            'date_end' => $end ? now()->modify($end) : null,
        ]);
    }

    private function discount(Product $p, ?CustomerGroup $g, float $value, int $qty = 1, string $type = 'fixed'): void
    {
        ProductDiscount::create([
            'product_id' => $p->id, 'customer_group_id' => $g?->id,
            'quantity' => $qty, 'price_type' => $type, 'value' => $value,
        ]);
    }

    private function price(Product $p, float $regular = 5000, int $qty = 1, ?CustomerGroup $g = null)
    {
        return PriceResolver::resolve($p->fresh(), $regular, $qty, $g?->id);
    }

    public function test_no_rules_pays_regular(): void
    {
        $r = $this->price($this->product());
        $this->assertSame(5000.0, $r->price);
        $this->assertNull($r->source);
    }

    public function test_special_fixed_3000_beats_discount_4500(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 3000);
        $this->discount($p, $this->resellers, 4500);
        $r = $this->price($p, 5000, 1, $this->resellers);
        $this->assertSame(3000.0, $r->price);
        $this->assertSame('special', $r->source);
        $this->assertNotNull($r->special);
    }

    public function test_special_4800_vs_reseller_4500(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 4800);
        $this->discount($p, $this->resellers, 4500);
        $this->assertSame(4800.0, $this->price($p, 5000, 1, $this->default)->price);
        $r = $this->price($p, 5000, 1, $this->resellers);
        $this->assertSame(4500.0, $r->price);
        $this->assertSame('discount', $r->source);
    }

    public function test_percent_special_on_variant(): void
    {
        $p = $this->product();
        $this->special($p, 'percent', 20);
        $this->assertSame(4800.0, $this->price($p, 6000)->price);
    }

    public function test_fixed_special_keeps_variant_difference(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 3000);
        $this->assertSame(5000.0, $this->price($p, 7000)->price);
    }

    public function test_overlapping_specials_lowest_wins(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 4000);
        $this->special($p, 'percent', 40);
        $this->special($p, 'fixed', 3500);
        $this->assertSame(3000.0, $this->price($p)->price);
    }

    public function test_expired_or_future_special_ignored(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 1000, '-10 days', '-1 day');
        $this->special($p, 'fixed', 2000, '+1 day', '+5 days');
        $r = $this->price($p);
        $this->assertSame(5000.0, $r->price);
        $this->assertNull($r->source);
    }

    public function test_discount_tier_applies_from_its_quantity(): void
    {
        $p = $this->product();
        $this->discount($p, $this->resellers, 4500);
        $this->discount($p, $this->resellers, 15, 10, 'percent');
        $this->assertSame(4500.0, $this->price($p, 5000, 1, $this->resellers)->price);
        $this->assertSame(4500.0, $this->price($p, 5000, 9, $this->resellers)->price);
        $this->assertSame(4250.0, $this->price($p, 5000, 10, $this->resellers)->price);
        $this->assertSame(5000.0, $this->price($p, 5000, 10, $this->default)->price);
    }

    public function test_all_groups_discount_vs_group_discount_lowest_wins(): void
    {
        $p = $this->product();
        $this->discount($p, null, 4700);
        $this->discount($p, $this->resellers, 4500);
        $this->assertSame(4500.0, $this->price($p, 5000, 1, $this->resellers)->price);
        $this->assertSame(4700.0, $this->price($p, 5000, 1, $this->default)->price);
        $this->assertSame(4700.0, $this->price($p)->price);
    }

    public function test_price_never_below_zero(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 500);
        $this->assertSame(0.0, $this->price($p, 4000)->price);
    }

    public function test_rule_not_below_regular_pays_regular_with_no_source(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 5000);
        $this->discount($p, $this->resellers, 6000);
        $r = $this->price($p, 5000, 1, $this->resellers);
        $this->assertSame(5000.0, $r->price);
        $this->assertNull($r->source);
        $this->assertNull($r->special);
        $this->assertNull($r->discount);
    }

    public function test_tie_between_special_and_discount_goes_to_special(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 4500);
        $this->discount($p, $this->resellers, 4500);
        $this->assertSame('special', $this->price($p, 5000, 1, $this->resellers)->source);
    }

    public function test_buying_more_never_costs_more_per_item(): void
    {
        $p = $this->product();
        $this->discount($p, $this->resellers, 4500);
        $this->discount($p, null, 4700, 5);
        $this->assertSame(4500.0, $this->price($p, 5000, 1, $this->resellers)->price);
        $this->assertSame(4500.0, $this->price($p, 5000, 5, $this->resellers)->price);
    }

    public function test_tiers_lists_quantity_steps(): void
    {
        $p = $this->product();
        $this->discount($p, $this->resellers, 4500);
        $this->discount($p, null, 4500, 5);
        $this->discount($p, $this->resellers, 15, 10, 'percent');
        $this->discount($p, $this->default, 1000, 20);

        $this->assertSame(
            [['qty' => 10, 'price' => 4250.0]],
            PriceResolver::tiers($p->fresh(), 5000, $this->resellers->id)
        );
        $this->assertSame(
            [['qty' => 5, 'price' => 4500.0], ['qty' => 20, 'price' => 1000.0]],
            PriceResolver::tiers($p->fresh(), 5000, $this->default->id)
        );
    }

    public function test_tiers_skip_quantity_one(): void
    {
        $p = $this->product();
        $this->discount($p, $this->resellers, 4500);
        $this->assertSame([], PriceResolver::tiers($p->fresh(), 5000, $this->resellers->id));
    }

    public function test_tiers_hidden_by_a_special_that_beats_them(): void
    {
        $p = $this->product();
        $this->special($p, 'fixed', 3000);
        $this->discount($p, null, 4500, 5);
        $this->assertSame([], PriceResolver::tiers($p->fresh(), 5000, $this->default->id));
    }
}
