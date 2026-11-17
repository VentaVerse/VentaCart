<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Catalog;

/** The pages the extension adds to the store's sitemap. */
class SampleSitemap
{
    /** @return \Generator<int, array<string, mixed>> */
    public static function urls(): \Generator
    {
        yield ['loc' => route('sample-extension.page.show', ['slug' => 'lamp']), 'lastmod' => '2026-01-31 09:30:00', 'changefreq' => 'weekly', 'priority' => '0.6'];
        yield ['loc' => route('sample-extension.page.show', ['slug' => 'shelf'])];
    }
}
