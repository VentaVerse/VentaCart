<?php

namespace Tests\Fixtures\Extensions\SampleExtension;

use App\Support\StorefrontPageType;
use Illuminate\Support\ServiceProvider;

/**
 * A small extension that uses every storefront hook core and the bundled
 * theme offer, so the tests can prove each one end to end: switching the
 * extension off or removing it takes every hook away again.
 */
class SampleExtensionServiceProvider extends ServiceProvider
{
    public const PAGE_TYPE = 'sample_page';

    public function boot(): void
    {
        $root = dirname(__DIR__);

        $this->loadViewsFrom($root . '/resources/views', 'sample-extension');
        $this->loadRoutesFrom($root . '/routes/web.php');

        StorefrontPageType::register(self::PAGE_TYPE, 'Sample pages', ['sample-extension.page.']);
    }
}
