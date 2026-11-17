<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * A store that upgrades keeps drawing its pages exactly as before. The main
 * content of the home, category and product pages of a small store on the
 * shipped defaults is compared with a recorded copy.
 *
 * To record the copies again after an intended change to these pages, run
 * this test once with XELGA_WRITE_GOLDEN=1; it writes the files and fails,
 * so a run that writes can never pass.
 */
class XelgaUnchangedPagesTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const DIR = __DIR__ . '/../fixtures/unchanged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        // Core's visibility scope steps aside for console runs; these are storefront requests.
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
        // Links carry the store's address; a fixed one keeps the copies the same on every machine.
        config(['app.url' => 'http://example.test']);
        URL::forceRootUrl('http://example.test');
    }

    public function test_existing_pages_render_the_same_main_content(): void
    {
        $store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        $laptops = Category::create(['store_id' => $store->id, 'name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);
        foreach (['DEMO-001' => 'Demo Laptop 13', 'DEMO-002' => 'Demo Laptop 14', 'DEMO-003' => 'Demo Laptop 15'] as $sku => $name) {
            $product = Product::create([
                'store_id' => $store->id, 'name' => $name, 'slug' => strtolower($sku), 'sku' => $sku,
                'price' => 45000, 'quantity' => 10, 'track_stock' => true, 'is_active' => true,
                'description' => '<p>A light laptop for work and travel.</p>',
            ]);
            $product->categories()->attach($laptops->id);
        }

        $pages = ['home' => '/', 'category' => '/category/laptops', 'product' => '/product/demo-001'];
        $write = (bool) getenv('XELGA_WRITE_GOLDEN');

        foreach ($pages as $name => $url) {
            $html = $this->mainContent($url);
            $file = self::DIR . "/{$name}.html";
            if ($write) {
                file_put_contents($file, $html);
                continue;
            }
            $this->assertFileExists($file, 'Record the pages first with XELGA_WRITE_GOLDEN=1.');
            $this->assertSame(file_get_contents($file), $html, "The main content of the {$name} page changed.");
        }

        if ($write) {
            $this->fail('Golden files written; run again without XELGA_WRITE_GOLDEN');
        }
    }

    /**
     * The HTML inside `<main id="main-content">`, without what differs on
     * every run: the form token, and the order of related products, which
     * core picks at random from the product's category.
     */
    private function mainContent(string $url): string
    {
        Storefront::forgetRequestState();
        $html = (string) $this->get($url)->assertOk()->getContent();

        $start = strpos($html, '<main id="main-content">');
        $this->assertNotFalse($start, "{$url} has no main content.");
        $start += strlen('<main id="main-content">');
        $end = strrpos($html, '</main>');
        $this->assertNotFalse($end);

        $main = (string) preg_replace('/name="_token" value="[^"]*"/', 'name="_token" value=""', substr($html, $start, $end - $start));

        return (string) preg_replace_callback(
            '#<!--xelga:([a-z0-9-]+)-->(?=<section[^>]*data-section-type="related-products").*?<!--/xelga:\1-->#s',
            function (array $m): string {
                preg_match_all('#<li><article class="card".*?</article>\s*</li>#s', $m[0], $cards);
                $sorted = $cards[0];
                sort($sorted);
                $i = 0;

                return (string) preg_replace_callback('#<li><article class="card".*?</article>\s*</li>#s', function () use ($sorted, &$i) {
                    return $sorted[$i++];
                }, $m[0]);
            },
            $main,
        );
    }
}
