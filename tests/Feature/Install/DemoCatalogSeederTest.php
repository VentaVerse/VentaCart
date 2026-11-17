<?php

namespace Tests\Feature\Install;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The demo catalog a fresh install opens on: prices a shop would ask in the
 * default currency, US dollars, US spelling in every seeded word, and slugs that themes
 * point at by name, so they must never drift.
 */
class DemoCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The seeder copies its photographs into storage_path(), which is the real
     * storage folder unless redirected. A throwaway one keeps the tests from
     * writing into the app's own public storage.
     */
    private string $storage;

    /**
     * Words spelled the British way. Matched as word stems, case-insensitive.
     */
    private const BRITISH = [
        'colour', 'centre', 'aluminium', 'catalogue', 'grey', 'cancelling',
        'favour', 'behaviour', 'metre', 'licence', 'organis', 'realise',
        'programme', 'travelled', 'travelling', 'jewellery', 'fibre', 'tyre',
        'analyse', 'optimise', 'customis', 'personalis',
    ];

    private const PRODUCT_SLUGS = [
        'aurora-15-pro',
        'aurora-15',
        'northwind-air-13',
        'northwind-studio-14',
        'lumen-27-inch-4k-display',
        'lumen-34-inch-ultrawide',
        'lumen-go-15-inch-portable',
        'kestrel-tab-11-pro',
        'kestrel-tab-air',
        'vireo-buds-pro',
        'vireo-usb-c-hub',
        'vireo-keys-and-mouse',
    ];

    private const CATEGORY_SLUGS = ['phones', 'computers', 'laptops', 'monitors', 'tablets', 'accessories'];

    private const BRAND_SLUGS = ['aurora', 'northwind', 'lumen', 'kestrel', 'vireo'];

    protected function setUp(): void
    {
        parent::setUp();

        $base = getenv('TMPDIR') ?: sys_get_temp_dir();
        $this->storage = rtrim($base, '/') . '/demo-catalog-' . bin2hex(random_bytes(6));
        mkdir($this->storage . '/app/public', 0775, true);
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->storage);

        parent::tearDown();
    }

    private function seedFresh(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(DemoCatalogSeeder::class);
    }

    public function test_slugs_stay_exactly_as_themes_reference_them(): void
    {
        $this->seedFresh();

        $this->assertSame(
            self::PRODUCT_SLUGS,
            Product::withoutGlobalScopes()->orderBy('sort_order')->pluck('slug')->all(),
        );
        $this->assertEqualsCanonicalizing(self::CATEGORY_SLUGS, Category::query()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(self::BRAND_SLUGS, Brand::query()->pluck('slug')->all());
    }

    public function test_prices_are_dollar_sized_and_look_like_shop_prices(): void
    {
        $this->seedFresh();

        $products = Product::withoutGlobalScopes()->get();
        $this->assertCount(12, $products);

        foreach ($products as $product) {
            $price = (float) $product->price;

            // Priced in the default currency, US dollars: a hub costs tens,
            // nothing costs thousands of dollars.
            $this->assertGreaterThanOrEqual(20, $price, "{$product->slug} is too cheap to be a dollar price");
            $this->assertLessThan(2000, $price, "{$product->slug} is not dollar-sized");
            $this->assertSame(9, (int) $price % 10, "{$product->slug} does not end in 9");
        }

        $phone = (float) $products->firstWhere('slug', 'aurora-15-pro')->price;
        $this->assertGreaterThanOrEqual(500, $phone, 'a flagship phone is a few hundred dollars or more');
        $this->assertLessThanOrEqual(2000, $phone);
        $this->assertSame(1199.0, $phone);
        $this->assertSame(69.0, (float) $products->firstWhere('slug', 'vireo-usb-c-hub')->price);

        $specials = DB::table('product_specials')->get();
        $this->assertCount(4, $specials);

        foreach ($specials as $special) {
            $base = (float) $products->firstWhere('id', $special->product_id)->price;

            $this->assertLessThan($base, (float) $special->price);
            $this->assertGreaterThan($base * 0.8, (float) $special->price, 'a demo special is a modest discount');
            $this->assertSame(9, (int) $special->price % 10);
        }
    }

    public function test_variant_prices_scale_with_the_base_price(): void
    {
        $this->seedFresh();

        $phone = Product::withoutGlobalScopes()->where('slug', 'aurora-15-pro')->firstOrFail();
        $variants = DB::table('product_variants')->where('product_id', $phone->id)->get();

        $this->assertCount(9, $variants);
        $this->assertEqualsCanonicalizing([0.0, 100.0, 300.0], $variants->pluck('price_modifier')->map(fn ($m) => (float) $m)->unique()->values()->all());

        foreach ($variants as $variant) {
            $this->assertSame((float) $phone->price + (float) $variant->price_modifier, (float) $variant->price);
            $this->assertSame(9, (int) $variant->price % 10);
        }

        $this->assertSame(
            ['Color', 'Storage'],
            DB::table('variant_types')->where('product_id', $phone->id)->orderBy('sort_order')->pluck('name')->all(),
        );
    }

    public function test_every_product_and_variant_costs_less_than_it_sells_for(): void
    {
        $this->seedFresh();

        $products = Product::withoutGlobalScopes()->with('specials')->get();
        $this->assertCount(12, $products);
        foreach ($products as $product) {
            $cost = (float) $product->cost;
            $this->assertGreaterThan(0, $cost, "{$product->name} has no cost");
            $this->assertLessThan((float) $product->price, $cost, "{$product->name} costs more than its price");
            foreach ($product->specials as $special) {
                $this->assertLessThan((float) $special->price, $cost, "{$product->name} costs more than its sale price");
            }
        }

        $variants = \App\Models\ProductVariant::all();
        $this->assertNotEmpty($variants);
        foreach ($variants as $variant) {
            $this->assertGreaterThan(0, (float) $variant->cost, "{$variant->sku} has no cost");
            $this->assertLessThan((float) $variant->price, (float) $variant->cost, "{$variant->sku} costs more than its price");
        }
    }

    public function test_no_seeded_text_uses_british_spelling(): void
    {
        $this->seedFresh();

        $text = collect()
            ->merge(Product::withoutGlobalScopes()->get(['name', 'description'])->flatMap(fn ($p) => [$p->name, $p->description]))
            ->merge(Brand::query()->get(['name', 'description'])->flatMap(fn ($b) => [$b->name, $b->description]))
            ->merge(Category::query()->get(['name', 'description'])->flatMap(fn ($c) => [$c->name, $c->description]))
            ->merge(DB::table('variant_types')->pluck('name'))
            ->merge(DB::table('variant_type_values')->pluck('value'))
            ->filter()
            ->implode("\n");

        $this->assertNotSame('', $text);

        foreach (self::BRITISH as $word) {
            $this->assertDoesNotMatchRegularExpression('/\b' . $word . '/i', $text, "seeded text spells {$word}");
        }
    }

    public function test_the_seeder_source_has_no_british_spelling_or_other_product_names(): void
    {
        $source = file_get_contents(database_path('seeders/DemoCatalogSeeder.php'));

        foreach ([...self::BRITISH, 'opencart', 'journal', 'pavilion'] as $word) {
            $this->assertDoesNotMatchRegularExpression('/\b' . $word . '/i', $source, "seeder source says {$word}");
        }
    }

    public function test_a_store_with_a_product_of_its_own_is_left_untouched(): void
    {
        $this->seed(StoreSeeder::class);

        $own = Product::withoutGlobalScopes()->create([
            'store_id' => 1,
            'name' => 'Merchant Item',
            'slug' => 'merchant-item',
            'sku' => 'DEMO-900',
            'price' => 150,
            'quantity' => 3,
            'is_active' => true,
        ]);

        $this->seed(DemoCatalogSeeder::class);

        $this->assertSame([$own->id], Product::withoutGlobalScopes()->pluck('id')->all());
        $this->assertSame(0, Category::query()->count());
        $this->assertSame(0, Brand::query()->count());
        $this->assertSame(0, DB::table('variant_types')->count());
        $this->assertSame(0, DB::table('product_specials')->count());
    }

    public function test_a_store_with_only_categories_is_left_untouched(): void
    {
        $this->seed(StoreSeeder::class);

        Category::create(['store_id' => 1, 'name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);

        $this->seed(DemoCatalogSeeder::class);

        $this->assertSame(0, Product::withoutGlobalScopes()->count());
        $this->assertSame(['shoes'], Category::query()->pluck('slug')->all());
        $this->assertSame(0, Brand::query()->count());
    }

    public function test_seeding_twice_adds_nothing_the_second_time(): void
    {
        $this->seedFresh();
        $this->seed(DemoCatalogSeeder::class);

        $this->assertSame(12, Product::withoutGlobalScopes()->count());
        $this->assertSame(6, Category::query()->count());
        $this->assertSame(5, Brand::query()->count());
    }
}
