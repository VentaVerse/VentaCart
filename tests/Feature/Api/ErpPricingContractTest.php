<?php

namespace Tests\Feature\Api;

use App\Models\ApiSyncLog;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductSpecial;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What an ERP can read and replace of a product's special prices and
 * discounts.
 *
 * A PUT replaces the whole list, so the cases that matter most are the ones
 * where a bad row must leave the stored list exactly as it was: nothing is
 * written until every row has passed.
 */
class ErpPricingContractTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Product $product;

    private CustomerGroup $retail;

    private CustomerGroup $resellers;

    private string $previousZone;

    protected function setUp(): void
    {
        parent::setUp();

        // What AppServiceProvider does with the store's time zone setting.
        $this->previousZone = date_default_timezone_get();
        config(['app.timezone' => 'Asia/Manila']);
        date_default_timezone_set('Asia/Manila');

        $this->store = Store::create(['name' => 'Demo Store', 'code' => 'demo']);
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->apiKey('erp-test-token');

        $this->retail = CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Retail', 'is_default' => true]);
        $this->resellers = CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Resellers', 'is_default' => false]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'name' => 'Demo Product',
            'slug' => 'demo-product',
            'sku' => 'DEMO-001',
            'price' => 5000,
            'quantity' => 10,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousZone);

        parent::tearDown();
    }

    private function api(): self
    {
        return $this->withToken('erp-test-token');
    }

    private function special(array $overrides = []): array
    {
        return array_merge([
            'type' => 'percent',
            'value' => 20,
            'starts_at' => '2026-11-28T00:00:00+08:00',
            'ends_at' => '2026-11-30T23:59:59+08:00',
        ], $overrides);
    }

    private function discount(array $overrides = []): array
    {
        return array_merge([
            'customer_group_id' => $this->resellers->id,
            'min_quantity' => 1,
            'type' => 'fixed',
            'value' => 4500,
        ], $overrides);
    }

    public function test_lists_customer_groups(): void
    {
        $this->api()->getJson('/api/v1/customer-groups')
            ->assertOk()
            ->assertExactJson([
                ['id' => $this->resellers->id, 'name' => 'Resellers', 'is_default' => false],
                ['id' => $this->retail->id, 'name' => 'Retail', 'is_default' => true],
            ]);
    }

    public function test_put_specials_replaces_list(): void
    {
        ProductSpecial::create([
            'product_id' => $this->product->id, 'price_type' => 'fixed', 'value' => 3000,
            'date_end' => '2026-12-31',
        ]);

        $response = $this->api()->putJson('/api/v1/products/DEMO-001/specials', [
            'specials' => [
                $this->special(),
                // An offset other than the store's is that same moment on
                // the store's clock.
                $this->special(['type' => 'fixed', 'value' => 4200, 'starts_at' => null, 'ends_at' => '2026-12-01T00:00:00Z']),
            ],
        ])->assertOk();

        $rows = $response->json('specials');
        $this->assertCount(2, $rows);
        $this->assertSame('percent', $rows[0]['type']);
        $this->assertEquals(20, $rows[0]['value']);
        $this->assertSame('2026-11-28T00:00:00+08:00', $rows[0]['starts_at']);
        $this->assertSame('2026-11-30T23:59:59+08:00', $rows[0]['ends_at']);
        $this->assertEquals(4000, $rows[0]['price']);
        $this->assertNotNull($rows[0]['updated_at']);

        $this->assertNull($rows[1]['starts_at']);
        $this->assertSame('2026-12-01T08:00:00+08:00', $rows[1]['ends_at']);
        $this->assertEquals(4200, $rows[1]['price']);

        $this->assertSame(2, ProductSpecial::where('product_id', $this->product->id)->count());
        $this->assertFalse(ProductSpecial::where('value', 3000)->exists());
        $this->assertSame('2026-12-01 08:00:00', ProductSpecial::where('value', 4200)->first()->getRawOriginal('date_end'));

        // A read returns the same list.
        $this->api()->getJson('/api/v1/products/DEMO-001/specials')
            ->assertOk()
            ->assertJsonPath('specials', $rows);
    }

    public function test_put_discounts_replaces_list(): void
    {
        $rows = $this->api()->putJson('/api/v1/products/DEMO-001/discounts', [
            'discounts' => [
                $this->discount(),
                $this->discount(['min_quantity' => 10, 'type' => 'percent', 'value' => 15]),
                $this->discount(['customer_group_id' => null, 'min_quantity' => 20, 'type' => 'percent', 'value' => 5]),
            ],
        ])->assertOk()->json('discounts');

        $this->assertCount(3, $rows);
        $this->assertSame($this->resellers->id, $rows[0]['customer_group_id']);
        $this->assertSame(1, $rows[0]['min_quantity']);
        $this->assertEquals(4500, $rows[0]['price']);
        $this->assertEquals(4250, $rows[1]['price']);
        $this->assertNull($rows[2]['customer_group_id']);
        $this->assertEquals(4750, $rows[2]['price']);

        $this->api()->getJson('/api/v1/products/DEMO-001/discounts')
            ->assertOk()
            ->assertJsonPath('discounts', $rows);
    }

    public function test_put_empty_list_clears(): void
    {
        ProductSpecial::create(['product_id' => $this->product->id, 'price_type' => 'fixed', 'value' => 3000, 'date_end' => '2026-12-31']);
        ProductDiscount::create(['product_id' => $this->product->id, 'customer_group_id' => null, 'quantity' => 1, 'price_type' => 'fixed', 'value' => 4000]);

        $this->api()->putJson('/api/v1/products/DEMO-001/specials', ['specials' => []])
            ->assertOk()->assertExactJson(['specials' => []]);
        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => []])
            ->assertOk()->assertExactJson(['discounts' => []]);

        $this->assertSame(0, ProductSpecial::count());
        $this->assertSame(0, ProductDiscount::count());
    }

    public function test_missing_list_is_422_not_a_clear(): void
    {
        ProductSpecial::create(['product_id' => $this->product->id, 'price_type' => 'fixed', 'value' => 3000, 'date_end' => '2026-12-31']);

        $this->api()->putJson('/api/v1/products/DEMO-001/specials', [])
            ->assertStatus(422)->assertJsonValidationErrors('specials');
        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['specials' => []])
            ->assertStatus(422)->assertJsonValidationErrors('discounts');

        $this->assertSame(1, ProductSpecial::count());
    }

    public function test_special_without_ends_at_is_422(): void
    {
        $special = $this->special();
        unset($special['ends_at']);

        $this->api()->putJson('/api/v1/products/DEMO-001/specials', ['specials' => [$special]])
            ->assertStatus(422)->assertJsonValidationErrors('specials.0.ends_at');

        $this->assertSame(0, ProductSpecial::count());
    }

    public function test_special_ending_before_it_starts_is_422(): void
    {
        $this->api()->putJson('/api/v1/products/DEMO-001/specials', ['specials' => [
            $this->special(['starts_at' => '2026-11-30T00:00:00+08:00', 'ends_at' => '2026-11-29T23:59:59+08:00']),
        ]])->assertStatus(422)->assertJsonValidationErrors('specials.0.ends_at');
    }

    public function test_time_without_offset_is_422(): void
    {
        $this->api()->putJson('/api/v1/products/DEMO-001/specials', ['specials' => [
            $this->special(['starts_at' => '2026-11-28T00:00:00', 'ends_at' => '2026-11-30']),
        ]])->assertStatus(422)->assertJsonValidationErrors(['specials.0.starts_at', 'specials.0.ends_at']);
    }

    public function test_percent_100_is_422(): void
    {
        $this->api()->putJson('/api/v1/products/DEMO-001/specials', ['specials' => [
            $this->special(['value' => 100]),
        ]])->assertStatus(422)->assertJsonValidationErrors('specials.0.value');

        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [
            $this->discount(['type' => 'percent', 'value' => 0]),
        ]])->assertStatus(422)->assertJsonValidationErrors('discounts.0.value');

        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [
            $this->discount(['type' => 'fixed', 'value' => -1]),
        ]])->assertStatus(422)->assertJsonValidationErrors('discounts.0.value');
    }

    public function test_more_than_fifty_rows_is_422(): void
    {
        $rows = [];
        for ($q = 1; $q <= 51; $q++) {
            $rows[] = $this->discount(['min_quantity' => $q]);
        }

        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => $rows])
            ->assertStatus(422)->assertJsonValidationErrors('discounts');
    }

    public function test_unknown_customer_group_is_422(): void
    {
        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [
            $this->discount(['customer_group_id' => 9999]),
        ]])->assertStatus(422)->assertJsonValidationErrors('discounts.0.customer_group_id');
    }

    public function test_duplicate_discount_group_quantity_is_422_and_writes_nothing(): void
    {
        ProductDiscount::create(['product_id' => $this->product->id, 'customer_group_id' => null, 'quantity' => 1, 'price_type' => 'fixed', 'value' => 4000]);

        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [
            $this->discount(['min_quantity' => 5]),
            $this->discount(['min_quantity' => 10]),
            $this->discount(['min_quantity' => 5, 'value' => 4400]),
        ]])->assertStatus(422)->assertJsonValidationErrors('discounts.2.min_quantity');

        $this->assertSame(1, ProductDiscount::count());
        $this->assertEquals(4000, ProductDiscount::first()->value);
    }

    public function test_null_group_twice_same_quantity_is_422(): void
    {
        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [
            $this->discount(['customer_group_id' => null, 'min_quantity' => 20]),
            $this->discount(['customer_group_id' => null, 'min_quantity' => 20, 'value' => 4000]),
        ]])->assertStatus(422)->assertJsonValidationErrors('discounts.1.min_quantity');

        // The same quantity for a named group and for all groups is two rules.
        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [
            $this->discount(['customer_group_id' => null, 'min_quantity' => 20]),
            $this->discount(['min_quantity' => 20, 'value' => 4000]),
        ]])->assertOk();

        $this->assertSame(2, ProductDiscount::count());
    }

    public function test_variant_sku_404_names_parent(): void
    {
        ProductVariant::create(['product_id' => $this->product->id, 'sku' => 'DEMO-001-512', 'price' => 5500, 'quantity' => 1]);

        foreach (['specials', 'discounts'] as $list) {
            $this->api()->getJson("/api/v1/products/DEMO-001-512/{$list}")
                ->assertStatus(404)
                ->assertExactJson(['error' => 'DEMO-001-512 is a variant; prices belong to product DEMO-001']);

            $this->api()->putJson("/api/v1/products/DEMO-001-512/{$list}", [$list => []])
                ->assertStatus(404)
                ->assertExactJson(['error' => 'DEMO-001-512 is a variant; prices belong to product DEMO-001']);
        }
    }

    public function test_unknown_sku_is_404(): void
    {
        $this->api()->getJson('/api/v1/products/NOPE/specials')
            ->assertStatus(404)->assertExactJson(['error' => 'Product not found']);
        $this->api()->putJson('/api/v1/products/NOPE/discounts', ['discounts' => []])
            ->assertStatus(404)->assertExactJson(['error' => 'Product not found']);
    }

    public function test_read_key_cannot_put(): void
    {
        $this->apiKey('catalogue-read-key', ['catalogue' => ['read']]);

        $this->withToken('catalogue-read-key')->getJson('/api/v1/customer-groups')->assertOk();
        $this->withToken('catalogue-read-key')->getJson('/api/v1/products/DEMO-001/specials')->assertOk();
        $this->withToken('catalogue-read-key')->getJson('/api/v1/products/DEMO-001/discounts')->assertOk();

        $this->withToken('catalogue-read-key')
            ->putJson('/api/v1/products/DEMO-001/specials', ['specials' => [$this->special()]])
            ->assertStatus(403);
        $this->withToken('catalogue-read-key')
            ->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [$this->discount()]])
            ->assertStatus(403);

        $this->assertSame(0, ProductSpecial::count());
        $this->assertSame(0, ProductDiscount::count());

        $this->apiKey('orders-key', ['orders' => ['read', 'write']]);
        $this->withToken('orders-key')->getJson('/api/v1/customer-groups')->assertStatus(403);
    }

    public function test_product_show_includes_specials_and_discounts(): void
    {
        $this->api()->putJson('/api/v1/products/DEMO-001/specials', ['specials' => [$this->special()]])->assertOk();
        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [$this->discount()]])->assertOk();

        $show = $this->api()->getJson('/api/v1/products/DEMO-001')->assertOk();

        $this->assertSame(
            $this->api()->getJson('/api/v1/products/DEMO-001/specials')->json('specials'),
            $show->json('specials'),
        );
        $this->assertSame(
            $this->api()->getJson('/api/v1/products/DEMO-001/discounts')->json('discounts'),
            $show->json('discounts'),
        );
        // Existing fields are untouched.
        $show->assertJsonPath('sku', 'DEMO-001')->assertJsonStructure(['price', 'variants', 'status']);
    }

    public function test_calls_are_logged(): void
    {
        ApiSyncLog::query()->delete();

        $this->api()->getJson('/api/v1/customer-groups')->assertOk();
        $this->api()->putJson('/api/v1/products/DEMO-001/specials', ['specials' => [$this->special()]])->assertOk();
        $this->api()->getJson('/api/v1/products/DEMO-001/discounts')->assertOk();
        $this->api()->putJson('/api/v1/products/DEMO-001/discounts', ['discounts' => [$this->discount(['value' => -5])]])->assertStatus(422);

        $logs = ApiSyncLog::orderBy('id')->get();
        $this->assertSame(
            [
                ['GET', 'api/v1/customer-groups', null, 200],
                ['PUT', 'api/v1/products/DEMO-001/specials', 'DEMO-001', 200],
                ['GET', 'api/v1/products/DEMO-001/discounts', 'DEMO-001', 200],
                ['PUT', 'api/v1/products/DEMO-001/discounts', 'DEMO-001', 422],
            ],
            $logs->map(fn ($l) => [$l->method, $l->endpoint, $l->sku, (int) $l->status_code])->all(),
        );
    }
}
