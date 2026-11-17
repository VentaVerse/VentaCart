<?php

namespace Tests\Fixtures\Extensions\SampleExtension;

use App\Support\AccountMenu;
use App\Support\CartGroups;
use App\Support\CheckoutPage;
use App\Support\OrderPanels;
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
        CartGroups::use(Cart\SampleCartGrouper::class);
        AccountMenu::add('sample-extension.account', 'Sample things', 'store');
        CheckoutPage::use(Http\SampleCheckoutController::class);
        OrderPanels::add(OrderPanels::ROW_AFTER, [Orders\SampleOrderPanels::class, 'rows']);
        OrderPanels::add(OrderPanels::DETAIL_SUMMARY, [Orders\SampleOrderPanels::class, 'summary']);
        OrderPanels::add(OrderPanels::DETAIL_ACTIONS, [Orders\SampleOrderPanels::class, 'actions']);
    }
}
