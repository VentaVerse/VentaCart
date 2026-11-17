<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Category;
use App\Services\ThemeService;
use App\Support\ProductCardLines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\HtmlString;
use Tests\Fixtures\Extensions\SampleExtension\Catalog\SampleCardLines;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * Lines extensions add to product cards (core's ProductCardLines), drawn
 * under the product name on every shared card: listings, product rows and
 * the wishlist. Each list asks the extension once, and a store with no
 * extension line pays nothing for it.
 */
class XelgaProductCardLinesTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const LINE = '<div class="card__ext"><div class="card__ext-line" data-ext="sample-extension">Sold by Demo Shop</div></div>';

    /** Xelga with the fixture extension, whose class loads once it is installed. */
    private function withExtension(): void
    {
        $this->setUpXelgaWithExtension();
        SampleCardLines::$calls = 0;
    }

    private function catalog(int $count = 24): Category
    {
        app(ThemeService::class)->set('category_page', 'products_per_page', '24');
        $category = Category::create(['store_id' => $this->store->id, 'name' => 'Demo Lamps', 'slug' => 'demo-lamps', 'is_active' => true]);
        for ($i = 1; $i <= $count; $i++) {
            $this->product('Demo Lamp ' . $i)->categories()->attach($category->id);
        }

        return $category;
    }

    /** The queries one request runs, after a first request warmed what any request caches. */
    private function queries(string $url): int
    {
        $this->page($url);
        $this->forgetXelgaState();
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->get($url)->assertOk();
        DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

        return $count;
    }

    private function showLines(bool $on): void
    {
        $settings = $this->defaults(Handles::SETTINGS);
        $settings['values']['show_extension_details'] = $on;
        $this->publishedDocument(Handles::SETTINGS, $settings);
    }

    public function test_the_line_is_drawn_under_the_name_on_a_category_grid_and_asked_for_once(): void
    {
        $this->withExtension();
        $category = $this->catalog();

        $html = $this->page('/category/' . $category->slug)->getContent();

        $this->assertSame(24, substr_count($html, self::LINE));
        $this->assertSame(1, SampleCardLines::$calls);
        $this->assertStringContainsString('data-card-ext="true"', $html);
        // Under the name, before the price.
        $this->assertMatchesRegularExpression('#class="card__title"[^>]*><a [^>]*>Demo Lamp 1</a></h3>\s*' . preg_quote(self::LINE, '#') . '#', $html);
        $card = substr($html, strpos($html, '>Demo Lamp 1</a>'));
        $this->assertLessThan(strpos($card, 'class="price'), strpos($card, 'card__ext'));
    }

    public function test_the_line_is_drawn_on_a_home_page_product_row_once_per_list(): void
    {
        $this->withExtension();
        $this->catalog(6);

        $html = $this->page('/')->getContent();

        // The shipped home page has one product row with products here (the
        // on-sale row is empty, and asks nobody).
        $this->assertGreaterThan(0, substr_count($html, self::LINE));
        $this->assertSame(1, SampleCardLines::$calls);
    }

    public function test_the_line_is_drawn_on_a_product_page_row(): void
    {
        $this->withExtension();
        $this->catalog(5);

        $html = $this->page(route('product.show', 'demo-lamp-1'))->getContent();

        $this->assertGreaterThan(0, substr_count($html, self::LINE));
        $this->assertStringNotContainsString('card__ext', substr($html, 0, strpos($html, 'class="card"')));
    }

    public function test_the_line_is_drawn_on_the_wishlist(): void
    {
        $this->withExtension();
        $product = $this->product('Demo Lamp');
        $this->postJson(route('wishlist.add'), ['product_id' => $product->id])->assertOk();

        $html = $this->page(route('wishlist.index'))->getContent();

        $this->assertSame(1, substr_count($html, self::LINE));
        $this->assertSame(1, SampleCardLines::$calls);
    }

    public function test_text_is_escaped_and_markup_comes_only_from_an_htmlable(): void
    {
        $this->withExtension();
        $category = $this->catalog(1);
        ProductCardLines::add('shipping', fn ($products) => $products->mapWithKeys(fn ($p) => [$p->id => '<img src=x onerror=alert(1)> & more'])->all());
        ProductCardLines::add('badge', fn ($products) => $products->mapWithKeys(fn ($p) => [$p->id => new HtmlString('<strong class="sample-badge">Top seller</strong>')])->all());

        $html = $this->page('/category/' . $category->slug)->getContent();

        $this->assertStringContainsString('<div class="card__ext-line" data-ext="shipping">&lt;img src=x onerror=alert(1)&gt; &amp; more</div>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('<div class="card__ext-line" data-ext="badge"><strong class="sample-badge">Top seller</strong></div>', $html);
    }

    public function test_the_theme_setting_off_draws_no_line_and_asks_nobody(): void
    {
        $this->withExtension();
        $category = $this->catalog(3);
        $this->showLines(false);

        $html = $this->page('/category/' . $category->slug)->getContent();

        $this->assertStringNotContainsString('card__ext', $html);
        $this->assertStringContainsString('data-card-ext="false"', $html);
        $this->assertSame(0, SampleCardLines::$calls);
    }

    public function test_the_extension_switched_off_draws_no_line(): void
    {
        $this->withExtension();
        $category = $this->catalog(3);
        $this->switchOffSampleExtension();

        $html = $this->page('/category/' . $category->slug)->getContent();

        $this->assertStringNotContainsString('card__ext', $html);
        $this->assertSame(0, SampleCardLines::$calls);

        $this->switchOnSampleExtension();
        $this->assertSame(3, substr_count($this->page('/category/' . $category->slug)->getContent(), self::LINE));
    }

    public function test_a_line_that_throws_leaves_the_page_and_the_other_lines_drawn(): void
    {
        Exceptions::fake();
        $this->withExtension();
        $category = $this->catalog(2);
        ProductCardLines::add('broken', fn () => throw new \RuntimeException('The seller list is unavailable.'));

        $html = $this->page('/category/' . $category->slug)->getContent();

        $this->assertSame(2, substr_count($html, self::LINE));
        $this->assertStringNotContainsString('data-ext="broken"', $html);
        Exceptions::assertReported(\RuntimeException::class);
    }

    public function test_lines_cost_no_query_of_their_own(): void
    {
        $this->withExtension();
        $category = $this->catalog();
        $this->postJson(route('wishlist.add'), ['product_id' => $category->products()->first()->id])->assertOk();
        $urls = ['/category/' . $category->slug, '/', route('wishlist.index')];

        // The fixture's line reads nothing, so a page draws with the same
        // queries with and without it: Xelga adds none to ask for lines.
        $with = array_map(fn ($url) => $this->queries($url), $urls);
        $this->assertGreaterThan(0, SampleCardLines::$calls);
        ProductCardLines::forget();
        SampleCardLines::$calls = 0;
        $without = array_map(fn ($url) => $this->queries($url), $urls);

        $this->assertSame($without, $with);
        $this->assertSame(0, SampleCardLines::$calls);
    }
}
