<?php

namespace Tests\Unit;

use App\Support\SitemapUrls;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Pages extensions add to the XML sitemap: only this store's pages, only
 * values the sitemap format allows, each address once, read one at a time.
 */
class SitemapUrlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://shop.example.test']);
    }

    /** @return list<array<string, mixed>> */
    private function entries(array $listed = []): array
    {
        return iterator_to_array(SitemapUrls::all($listed), false);
    }

    public function test_without_registrations_there_are_no_entries(): void
    {
        $this->assertFalse(SitemapUrls::has());
        $this->assertSame([], $this->entries());
    }

    public function test_entries_are_checked_and_written_in_the_sitemap_format(): void
    {
        SitemapUrls::add('shops', fn () => [
            ['loc' => 'https://shop.example.test/shop/demo-shop', 'lastmod' => CarbonImmutable::parse('2026-01-31 09:30:00', 'Asia/Manila'), 'changefreq' => 'weekly', 'priority' => '0.6'],
            ['loc' => 'http://SHOP.example.test:80/shop/second', 'lastmod' => '2026-02-01', 'priority' => 1.5],
            ['loc' => 'https://shop.example.test/shop/third', 'priority' => -2],
            ['loc' => 'https://shop.example.test/shop/fourth', 'priority' => 0.85],
            ['loc' => 'https://shop.example.test/shop/fifth'],
        ]);

        $this->assertSame([
            ['loc' => 'https://shop.example.test/shop/demo-shop', 'lastmod' => '2026-01-31T09:30:00+08:00', 'changefreq' => 'weekly', 'priority' => '0.6'],
            ['loc' => 'http://SHOP.example.test:80/shop/second', 'lastmod' => CarbonImmutable::parse('2026-02-01')->toW3cString(), 'changefreq' => null, 'priority' => '1.0'],
            ['loc' => 'https://shop.example.test/shop/third', 'lastmod' => null, 'changefreq' => null, 'priority' => '0.0'],
            ['loc' => 'https://shop.example.test/shop/fourth', 'lastmod' => null, 'changefreq' => null, 'priority' => '0.85'],
            ['loc' => 'https://shop.example.test/shop/fifth', 'lastmod' => null, 'changefreq' => null, 'priority' => null],
        ], $this->entries());
    }

    public function test_entries_that_are_not_pages_of_this_store_or_break_the_format_are_refused(): void
    {
        Log::spy();
        SitemapUrls::add('shops', fn () => [
            ['loc' => 'https://other.example.com/shop/demo-shop'],
            ['loc' => 'javascript:alert(1)'],
            ['loc' => 'ftp://shop.example.test/file'],
            ['loc' => '//shop.example.test/shop/no-scheme'],
            ['loc' => '/shop/relative'],
            ['loc' => 'https://shop.example.test.example.com/shop/lookalike'],
            ['loc' => 'https://user:secret@shop.example.test/shop/with-login'],
            ['loc' => 'https://shop.example.test:8443/shop/other-port'],
            ['loc' => 'https://shop.example.test/shop/with-fragment#top'],
            ['loc' => 'https://shop.example.test/' . str_repeat('a', 2100)],
            ['loc' => 'https://shop.example.test/shop/bad-priority', 'priority' => 'high'],
            ['loc' => 'https://shop.example.test/shop/bad-frequency', 'changefreq' => 'fortnightly'],
            ['loc' => 'https://shop.example.test/shop/bad-date', 'lastmod' => 'not a date'],
            ['loc' => ['https://shop.example.test/shop/not-a-string']],
            'https://shop.example.test/shop/not-an-entry',
            ['loc' => 'https://shop.example.test/shop/kept'],
        ]);

        $this->assertSame(['https://shop.example.test/shop/kept'], array_column($this->entries(), 'loc'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context === ['source' => 'shops', 'skipped' => 15])->once();
    }

    public function test_each_address_is_listed_once_also_against_the_pages_already_listed(): void
    {
        SitemapUrls::add('shops', fn () => [
            ['loc' => 'https://shop.example.test/product/demo-mug'],
            ['loc' => 'https://shop.example.test/shop/demo-shop'],
            ['loc' => 'https://shop.example.test/shop/demo-shop', 'priority' => '0.9'],
        ]);
        SitemapUrls::add('sellers', fn () => [
            ['loc' => 'https://shop.example.test/shop/demo-shop'],
            ['loc' => 'https://shop.example.test/seller/maria-santos'],
        ]);

        $this->assertSame(
            ['https://shop.example.test/shop/demo-shop', 'https://shop.example.test/seller/maria-santos'],
            array_column($this->entries(['https://shop.example.test/product/demo-mug' => true]), 'loc')
        );
    }

    public function test_a_source_that_throws_is_reported_and_the_others_still_listed(): void
    {
        Exceptions::fake();
        SitemapUrls::add('broken', function () {
            yield ['loc' => 'https://shop.example.test/shop/before'];
            throw new \RuntimeException('The shop list is unavailable.');
        });
        SitemapUrls::add('thrower', fn () => throw new \RuntimeException('No shops.'));
        SitemapUrls::add('shops', fn () => [['loc' => 'https://shop.example.test/shop/demo-shop']]);

        $this->assertSame(
            ['https://shop.example.test/shop/before', 'https://shop.example.test/shop/demo-shop'],
            array_column($this->entries(), 'loc')
        );
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'The shop list is unavailable.');
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'No shops.');
    }

    public function test_a_bad_or_taken_name_is_refused(): void
    {
        Log::spy();
        SitemapUrls::add('Shops', fn () => [['loc' => 'https://shop.example.test/a']]);
        SitemapUrls::add('shops', fn () => [['loc' => 'https://shop.example.test/b']]);
        SitemapUrls::add('shops', fn () => [['loc' => 'https://shop.example.test/c']]);

        $this->assertSame(['https://shop.example.test/b'], array_column($this->entries(), 'loc'));
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_entries_are_read_one_at_a_time(): void
    {
        $made = 0;
        SitemapUrls::add('shops', function () use (&$made) {
            for ($i = 1; $i <= 10000; $i++) {
                $made++;
                yield ['loc' => 'https://shop.example.test/shop/demo-shop-' . $i, 'lastmod' => '2026-01-31', 'priority' => '0.5'];
            }
        });

        $all = SitemapUrls::all();
        $this->assertSame('https://shop.example.test/shop/demo-shop-1', $all->current()['loc']);
        $this->assertSame(1, $made);

        $before = memory_get_usage();
        $count = 1;
        $peak = 0;
        for ($all->next(); $all->valid(); $all->next()) {
            $count++;
            $peak = max($peak, memory_get_usage() - $before);
        }

        $this->assertSame(10000, $count);
        $this->assertSame(10000, $made);
        // Only a short hash of each address is kept, to list it once.
        $this->assertLessThan(4 * 1024 * 1024, $peak);
    }
}
