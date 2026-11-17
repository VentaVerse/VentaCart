<?php

namespace Tests\Fixtures\Extensions\SampleExtension;

use App\Support\AccountMenu;
use App\Support\CartGroups;
use App\Support\CheckoutPage;
use App\Support\OrderPanels;
use App\Support\ProductCardLines;
use App\Support\SitemapUrls;
use App\Support\StorefrontPageType;
use Illuminate\Support\Facades\Event;
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

        // Xelga's hooks: no Xelga class is needed here, and without Xelga
        // the event is never fired.
        Event::listen('xelga.extend', function ($xelga) use ($root): void {
            $xelga->parts('sample-extension', $root . '/resources/xelga');
            $xelga->previewUrl(self::PAGE_TYPE, fn (): ?string => route('sample-extension.page.show', ['slug' => 'lamp']));
        });

        $this->registerHooks();
    }

    /** Core's hooks, which live in static lists. */
    public function registerHooks(): void
    {
        StorefrontPageType::register(self::PAGE_TYPE, 'Sample pages', ['sample-extension.page.']);
        CartGroups::use(Cart\SampleCartGrouper::class);
        AccountMenu::add('sample-extension.account', 'Sample things', 'store');
        CheckoutPage::use(Http\SampleCheckoutController::class);
        OrderPanels::add(OrderPanels::ROW_AFTER, [Orders\SampleOrderPanels::class, 'rows']);
        OrderPanels::add(OrderPanels::DETAIL_SUMMARY, [Orders\SampleOrderPanels::class, 'summary']);
        OrderPanels::add(OrderPanels::DETAIL_ACTIONS, [Orders\SampleOrderPanels::class, 'actions']);
        ProductCardLines::add('sample-extension', [Catalog\SampleCardLines::class, 'lines']);
        SitemapUrls::add('sample-extension', [Catalog\SampleSitemap::class, 'urls']);
    }
}
