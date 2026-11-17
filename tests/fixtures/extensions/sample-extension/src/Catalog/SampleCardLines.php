<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;

/** The line the extension adds to every product card. */
class SampleCardLines
{
    /** How many times the line ran, so a test can see it runs once per list of cards. */
    public static int $calls = 0;

    /**
     * A plain string, so core escapes it.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, string>
     */
    public static function lines(Collection $products): array
    {
        self::$calls++;

        return $products->mapWithKeys(fn (Product $product) => [(int) $product->id => 'Sold by Demo Shop'])->all();
    }
}
