<?php

namespace Tests\Feature\Catalog;

use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductSpecial;
use App\Models\Store;
use App\Models\User;
use App\Services\CartService;
use App\Support\DateWindow;
use App\Support\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A special runs from its start moment to its end moment, inclusive, on the
 * store's clock. A plain date covers the whole day: a start at 00:00:00 and
 * an end at 23:59:59. The card, the product page, the listing filters and
 * the cart all answer "is it on now" the same way.
 *
 * The store here runs on Asia/Manila (UTC+8), so a mix-up between UTC and
 * store time shows up as an eight-hour error.
 */
class SpecialDateWindowTest extends TestCase
{
    use RefreshDatabase;

    private const ZONE = 'Asia/Manila';

    private string $previousZone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // What AppServiceProvider does with the store's timezone setting.
        $this->previousZone = date_default_timezone_get();
        config(['app.timezone' => self::ZONE]);
        date_default_timezone_set(self::ZONE);

        Store::firstOrCreate(['code' => 'test'], ['name' => 'Demo Store']);
        CustomerGroup::create(['store_id' => 1, 'name' => 'Default', 'is_default' => true]);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousZone);
        parent::tearDown();
    }

    private function at(string $storeTime): Carbon
    {
        return Carbon::parse($storeTime, self::ZONE);
    }

    private function product(): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => 'Demo Kettle', 'slug' => 'demo-kettle', 'sku' => 'DEMO-001',
            'price' => 1000, 'quantity' => 10, 'is_active' => true,
        ]);
    }

    private function special(Product $product, mixed $start, mixed $end, float $price = 800): ProductSpecial
    {
        return ProductSpecial::create([
            'product_id' => $product->id, 'customer_group_id' => null, 'price' => $price, 'quantity' => 1,
            'display_mode' => 'sale', 'priority' => 1, 'date_start' => $start, 'date_end' => $end,
        ]);
    }

    /**
     * The price every surface would show or charge at this moment: the
     * display price, the cart's resolved price, and whether the listing
     * filters (the SQL scope) count the product as on sale. They must agree.
     *
     * @return array{display: float, cart: float, listed: bool}
     */
    private function pricesAt(Product $product, string $storeTime): array
    {
        $this->travelTo($this->at($storeTime));
        $fresh = $product->fresh();

        $display = Pricing::display($fresh)->price;
        $cart = CartService::resolvePrice($fresh, (float) $fresh->price);
        $listed = Product::whereKey($product->id)
            ->whereHas('specials', fn ($q) => $q->where('display_mode', 'sale')->activeAt())
            ->exists();

        $this->assertSame($display, $cart, "Display and cart prices disagree at {$storeTime}.");
        $this->assertSame($display < 1000.0, $listed, "The listing filter disagrees with the price at {$storeTime}.");

        return ['display' => $display, 'cart' => $cart, 'listed' => $listed];
    }

    public function test_a_sale_ending_today_runs_through_the_end_of_today(): void
    {
        $product = $this->product();
        $this->special($product, null, '2026-10-31');

        $this->assertSame(800.0, $this->pricesAt($product, '2026-10-31 00:00:00')['display']);
        $this->assertSame(800.0, $this->pricesAt($product, '2026-10-31 23:00:00')['display']);
        $this->assertSame(800.0, $this->pricesAt($product, '2026-10-31 23:59:59')['display']);
        $this->assertSame(1000.0, $this->pricesAt($product, '2026-11-01 00:00:00')['display']);
        $this->assertSame(1000.0, $this->pricesAt($product, '2026-11-01 00:01:00')['display']);
    }

    public function test_a_sale_with_an_end_time_stops_at_that_time(): void
    {
        $product = $this->product();
        $this->special($product, '2026-10-25 18:00', '2026-10-31 21:30');

        $this->assertSame(1000.0, $this->pricesAt($product, '2026-10-25 17:59:59')['display']);
        $this->assertSame(800.0, $this->pricesAt($product, '2026-10-25 18:00:00')['display']);
        $this->assertSame(800.0, $this->pricesAt($product, '2026-10-31 21:29:00')['display']);
        $this->assertSame(800.0, $this->pricesAt($product, '2026-10-31 21:30:00')['display']);
        $this->assertSame(1000.0, $this->pricesAt($product, '2026-10-31 21:30:01')['display']);
        $this->assertSame(1000.0, $this->pricesAt($product, '2026-10-31 21:31:00')['display']);
    }

    public function test_a_date_only_start_opens_at_midnight_of_that_day(): void
    {
        $product = $this->product();
        $this->special($product, '2026-10-25', null);

        $this->assertSame(1000.0, $this->pricesAt($product, '2026-10-24 23:59:59')['display']);
        $this->assertSame(800.0, $this->pricesAt($product, '2026-10-25 00:00:00')['display']);
    }

    public function test_display_price_and_cart_agree_at_every_boundary(): void
    {
        $product = $this->product();
        $this->special($product, '2026-10-25 18:00', '2026-10-31 21:30', 800);
        $this->special($product, '2026-10-31 21:31', '2026-11-02', 700);

        $expected = [
            '2026-10-25 17:59:59' => 1000.0,
            '2026-10-25 18:00:00' => 800.0,
            '2026-10-31 21:30:00' => 800.0,
            '2026-10-31 21:30:30' => 1000.0,
            '2026-10-31 21:31:00' => 700.0,
            '2026-11-02 23:59:59' => 700.0,
            '2026-11-03 00:00:00' => 1000.0,
        ];

        foreach ($expected as $moment => $price) {
            $this->assertSame($price, $this->pricesAt($product, $moment)['display'], "Wrong price at {$moment}.");
        }
    }

    public function test_plain_dates_from_any_writer_mean_the_whole_day(): void
    {
        $product = $this->product();

        // An import, a seeder, or an integration that sends a plain date.
        $special = $this->special($product, '2026-10-25', '2026-10-31');
        $this->assertSame('2026-10-25 00:00:00', DB::table('product_specials')->where('id', $special->id)->value('date_start'));
        $this->assertSame('2026-10-31 23:59:59', DB::table('product_specials')->where('id', $special->id)->value('date_end'));

        // A value with a time keeps it, including an explicit midnight.
        $special->update(['date_start' => '2026-10-25 18:00', 'date_end' => '2026-11-01 00:00:00']);
        $this->assertSame('2026-10-25 18:00:00', DB::table('product_specials')->where('id', $special->id)->value('date_start'));
        $this->assertSame('2026-11-01 00:00:00', DB::table('product_specials')->where('id', $special->id)->value('date_end'));

        // A value with its own offset is that moment, stored on the store's clock.
        $special->update(['date_end' => '2026-10-31T13:30:00Z']);
        $this->assertSame('2026-10-31 21:30:00', DB::table('product_specials')->where('id', $special->id)->value('date_end'));

        // A Carbon in another zone is converted, not reread as store time.
        $special->update(['date_end' => Carbon::parse('2026-10-31 13:30:00', 'UTC')]);
        $this->assertSame('2026-10-31 21:30:00', DB::table('product_specials')->where('id', $special->id)->value('date_end'));

        $special->update(['date_start' => null, 'date_end' => '']);
        $this->assertNull(DB::table('product_specials')->where('id', $special->id)->value('date_start'));
        $this->assertNull(DB::table('product_specials')->where('id', $special->id)->value('date_end'));
    }

    public function test_an_impossible_date_is_refused_not_stored(): void
    {
        $this->expectException(\Carbon\Exceptions\InvalidFormatException::class);
        DateWindow::end('2026-02-31');
    }

    public function test_the_end_moment_reaches_countdowns_with_the_store_offset(): void
    {
        $product = $this->product();
        $this->special($product, null, '2026-10-31 21:30');
        $this->travelTo($this->at('2026-10-30 12:00'));

        $price = Pricing::display($product->fresh());
        $this->assertSame('2026-10-31T21:30:00+08:00', $price->endsAtIso());
        $this->assertSame('2026-10-31T21:30:00+08:00', $price->toArray()['ends_at']);
        $this->assertTrue($price->endsAt->equalTo(Carbon::parse('2026-10-31 13:30:00', 'UTC')));

        $badges = app(\App\Services\BadgeService::class)->getBadgesForProduct($product->fresh());
        $sale = collect($badges)->firstWhere('type', 'sale');
        $this->assertSame('2026-10-31T21:30:00+08:00', $sale['end_date'] ?? null);

        // A date-only end counts down to the last second of that day.
        $other = Product::create(['store_id' => 1, 'name' => 'Demo Mug', 'slug' => 'demo-mug', 'sku' => 'DEMO-002', 'price' => 500, 'quantity' => 5, 'is_active' => true]);
        $this->special($other, null, '2026-10-31', 400);
        $this->assertSame('2026-10-31T23:59:59+08:00', Pricing::display($other->fresh())->endsAtIso());
    }

    public function test_the_store_clock_decides_not_the_server_clock(): void
    {
        $product = $this->product();
        $this->special($product, null, '2026-10-31 21:30');

        // 13:29 UTC is 21:29 in the store: still on. 13:31 UTC is 21:31: over.
        // The frozen clock is handed over in the store zone because Carbon
        // reads stored datetimes in the zone of a frozen test clock, which a
        // live clock never does; the instant is the same.
        $this->travelTo(Carbon::parse('2026-10-31 13:29:00', 'UTC')->setTimezone(self::ZONE));
        $this->assertSame(800.0, Pricing::display($product->fresh())->price);
        $this->assertTrue(ProductSpecial::activeAt()->exists());
        $this->assertTrue(ProductSpecial::activeAt(Carbon::parse('2026-10-31 13:29:00', 'UTC'))->exists());

        $this->travelTo(Carbon::parse('2026-10-31 13:31:00', 'UTC')->setTimezone(self::ZONE));
        $this->assertSame(1000.0, Pricing::display($product->fresh())->price);
        $this->assertFalse(ProductSpecial::activeAt()->exists());
        $this->assertFalse(ProductSpecial::activeAt(Carbon::parse('2026-10-31 13:31:00', 'UTC'))->exists());
    }

    public function test_the_admin_form_saves_a_date_with_or_without_a_time(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();

        $this->put(route('admin.products.update', $product), [
            'store_id' => 1, 'name' => 'Demo Kettle', 'price' => 1000, 'is_active' => '1',
            'specials' => [
                ['price' => '800', 'display_mode' => 'sale', 'priority' => '0', 'quantity' => '1',
                    'date_start' => '2026-10-25', 'time_start' => '18:00', 'date_end' => '2026-10-31', 'time_end' => '21:30'],
                ['price' => '850', 'display_mode' => 'sale', 'priority' => '1', 'quantity' => '1',
                    'date_start' => '2026-10-25', 'time_start' => '', 'date_end' => '2026-10-31', 'time_end' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rows = DB::table('product_specials')->where('product_id', $product->id)->orderBy('priority')->get(['date_start', 'date_end']);
        $this->assertSame(['2026-10-25 18:00:00', '2026-10-31 21:30:00'], [$rows[0]->date_start, $rows[0]->date_end]);
        $this->assertSame(['2026-10-25 00:00:00', '2026-10-31 23:59:59'], [$rows[1]->date_start, $rows[1]->date_end]);

        // Editing again shows the set times, and leaves the whole-day ones empty
        // so a second save keeps them whole days.
        $html = $this->get(route('admin.products.edit', $product))->assertOk()->getContent();
        $this->assertStringContainsString('"time_start":"18:00"', $html);
        $this->assertStringContainsString('"time_end":"21:30"', $html);
        $this->assertStringContainsString('"date_end":"2026-10-31","time_end":""', $html);
        $this->assertStringContainsString(self::ZONE, $html, 'The form names the store time zone.');
    }

    public function test_the_admin_form_refuses_an_end_before_the_start_or_a_bad_time(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        $base = ['store_id' => 1, 'name' => 'Demo Kettle', 'price' => 1000, 'is_active' => '1'];

        $this->put(route('admin.products.update', $product), $base + ['specials' => [
            ['price' => '800', 'date_start' => '2026-10-31', 'time_start' => '22:00', 'date_end' => '2026-10-31', 'time_end' => '21:30'],
        ]])->assertSessionHasErrors('specials.0.date_end');

        $this->put(route('admin.products.update', $product), $base + ['specials' => [
            ['price' => '800', 'date_end' => '2026-10-31', 'time_end' => '25:00'],
        ]])->assertSessionHasErrors('specials.0.time_end');

        $this->put(route('admin.products.update', $product), $base + ['specials' => [
            ['price' => '800', 'time_end' => '21:30'],
        ]])->assertSessionHasErrors('specials.0.date_end');

        // A sale on a single day, start and end dates the same, is fine.
        $this->put(route('admin.products.update', $product), $base + ['specials' => [
            ['price' => '800', 'date_start' => '2026-10-31', 'date_end' => '2026-10-31'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(1, ProductSpecial::where('product_id', $product->id)->count());
    }
}
