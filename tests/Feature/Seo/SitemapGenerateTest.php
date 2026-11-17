<?php

namespace Tests\Feature\Seo;

use App\Models\Product;
use App\Models\Store;
use App\Services\ExtensionManager;
use App\Support\SitemapUrls;
use Extensions\Seo\Console\GenerateSitemap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;

/**
 * The SEO extension's XML sitemap: core's pages, then the pages
 * extensions add through SitemapUrls, written as they are read and split
 * into numbered files past the format's 50,000 addresses.
 */
class SitemapGenerateTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://shop.example.test']);
        URL::forceRootUrl('https://shop.example.test');
        URL::forceScheme('https');
        $extensions = app(ExtensionManager::class);
        $extensions->install('seo');
        $extensions->bootEnabled();

        $store = Store::create(['name' => 'Demo Store', 'code' => 'demo']);
        Product::create(['store_id' => $store->id, 'name' => 'Demo Mug', 'slug' => 'demo-mug', 'sku' => 'DEMO-001', 'price' => 100, 'quantity' => 5, 'is_active' => true]);

        $this->dir = storage_path('framework/testing/sitemap-' . getmypid());
        File::ensureDirectoryExists($this->dir);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->dir));
    }

    /** The command run directly: its provider booted after the console registered commands. */
    private function generate(): string
    {
        $command = new GenerateSitemap();
        $command->setLaravel($this->app);
        $output = new BufferedOutput();
        $this->assertSame(0, $command->run(new ArrayInput(['--path' => $this->dir . '/sitemap.xml']), $output));

        return $output->fetch();
    }

    /** @return list<string> */
    private function locs(string $file = 'sitemap.xml'): array
    {
        preg_match_all('#<loc>(.*?)</loc>#', (string) file_get_contents($this->dir . '/' . $file), $m);

        return $m[1];
    }

    public function test_without_registrations_the_sitemap_lists_core_pages_only(): void
    {
        $this->assertStringContainsString('Sitemap generated with 4 URLs', $this->generate());

        $this->assertSame([
            'https://shop.example.test/',
            'https://shop.example.test/product/demo-mug',
            'https://shop.example.test/brand',
            'https://shop.example.test/contact',
        ], $this->locs());
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', (string) file_get_contents($this->dir . '/sitemap.xml'));
        $this->assertSame(['sitemap.xml'], array_map('basename', File::files($this->dir)));
    }

    public function test_the_pages_an_extension_adds_follow_core_pages(): void
    {
        $this->installSampleExtension();

        $this->generate();

        $xml = (string) file_get_contents($this->dir . '/sitemap.xml');
        $this->assertSame([
            'https://shop.example.test/',
            'https://shop.example.test/product/demo-mug',
            'https://shop.example.test/brand',
            'https://shop.example.test/contact',
            'https://shop.example.test/sample-extension/things/lamp',
            'https://shop.example.test/sample-extension/things/shelf',
        ], $this->locs());
        $this->assertStringContainsString(
            "  <url>\n    <loc>https://shop.example.test/sample-extension/things/lamp</loc>\n    <lastmod>" . \Carbon\CarbonImmutable::parse('2026-01-31 09:30:00')->toW3cString() . "</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.6</priority>\n  </url>\n",
            $xml
        );
        $this->assertStringContainsString("  <url>\n    <loc>https://shop.example.test/sample-extension/things/shelf</loc>\n  </url>\n", $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_an_extension_cannot_repeat_a_core_page_or_add_another_site(): void
    {
        SitemapUrls::add('shops', fn () => [
            ['loc' => 'https://shop.example.test/product/demo-mug', 'priority' => '1.0'],
            ['loc' => 'https://other.example.com/shop/demo-shop'],
            ['loc' => 'javascript:alert(1)'],
            ['loc' => 'https://shop.example.test/shop/demo-shop?from=a&to=b', 'priority' => 'top'],
            ['loc' => 'https://shop.example.test/shop/demo-shop?from=a&to=b'],
        ]);

        $this->generate();

        $locs = $this->locs();
        $this->assertSame(1, count(array_keys($locs, 'https://shop.example.test/product/demo-mug', true)));
        $this->assertSame('https://shop.example.test/shop/demo-shop?from=a&amp;to=b', end($locs));
        $this->assertCount(5, $locs);
    }

    public function test_the_extension_switched_off_adds_nothing(): void
    {
        $this->installSampleExtension();
        $this->switchOffSampleExtension();

        $this->generate();

        $this->assertCount(4, $this->locs());
    }

    public function test_past_fifty_thousand_addresses_the_sitemap_becomes_an_index_of_numbered_files(): void
    {
        // A file a bigger earlier run left behind goes.
        file_put_contents($this->dir . '/sitemap-3.xml', 'stale');
        SitemapUrls::add('shops', function () {
            for ($i = 1; $i <= GenerateSitemap::PER_FILE; $i++) {
                yield ['loc' => 'https://shop.example.test/shop/demo-shop-' . $i];
            }
        });

        $this->assertStringContainsString('Sitemap generated with ' . (GenerateSitemap::PER_FILE + 4) . ' URLs', $this->generate());

        $files = array_map('basename', File::files($this->dir));
        sort($files);
        $this->assertSame(['sitemap-1.xml', 'sitemap-2.xml', 'sitemap.xml'], $files);
        $index = (string) file_get_contents($this->dir . '/sitemap.xml');
        $this->assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $index);
        $this->assertSame(['https://shop.example.test/sitemap-1.xml', 'https://shop.example.test/sitemap-2.xml'], $this->locs());
        $this->assertCount(GenerateSitemap::PER_FILE, $this->locs('sitemap-1.xml'));
        $this->assertSame(['https://shop.example.test/shop/demo-shop-49997', 'https://shop.example.test/shop/demo-shop-49998', 'https://shop.example.test/shop/demo-shop-49999', 'https://shop.example.test/shop/demo-shop-50000'], $this->locs('sitemap-2.xml'));

        // Back under the limit, the sitemap is one file again.
        SitemapUrls::forget();
        $this->generate();
        $this->assertSame(['sitemap.xml'], array_map('basename', File::files($this->dir)));
    }
}
