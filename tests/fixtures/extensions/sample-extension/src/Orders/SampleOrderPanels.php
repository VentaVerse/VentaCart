<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Orders;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/** What the extension adds to the customer's order pages. */
class SampleOrderPanels
{
    /** How many times the list panel ran, so a test can see it runs once per page. */
    public static int $listCalls = 0;

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, HtmlString>
     */
    public static function rows(Collection $orders): array
    {
        self::$listCalls++;

        return $orders->mapWithKeys(fn (Order $order) => [
            (int) $order->id => new HtmlString('<p data-sample-row="' . e($order->id) . '">Sold by Demo Store</p>'),
        ])->all();
    }

    public static function summary(Order $order): HtmlString
    {
        return new HtmlString('<p data-sample-summary>Order ' . e($order->id) . ' is sold by Demo Store</p>');
    }

    public static function actions(Order $order): string
    {
        return '<a data-sample-action href="' . e(url('/sample-extension/returns/' . $order->id)) . '">Return an item</a>';
    }
}
