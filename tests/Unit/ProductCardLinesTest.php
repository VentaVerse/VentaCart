<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ProductCardLines;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * Lines extensions add to product cards: one call per list of cards, text
 * escaped, markup only from an Htmlable, and a failing extension left out.
 */
class ProductCardLinesTest extends TestCase
{
    /** @return Collection<int, Product> */
    private function products(int ...$ids): Collection
    {
        return collect($ids)->map(fn (int $id) => (new Product())->forceFill(['id' => $id, 'name' => 'Demo ' . $id]));
    }

    public function test_without_registrations_nothing_is_drawn(): void
    {
        $this->assertFalse(ProductCardLines::has());
        $this->assertSame([], ProductCardLines::for($this->products(1, 2)));
    }

    public function test_each_line_runs_once_per_list_and_lines_are_joined_in_the_order_added(): void
    {
        $calls = ['seller' => 0, 'ships' => 0];
        ProductCardLines::add('seller', function (Collection $products) use (&$calls) {
            $calls['seller']++;

            return $products->mapWithKeys(fn (Product $p) => [$p->id => 'Sold by Demo Shop'])->all();
        });
        ProductCardLines::add('ships', function (Collection $products) use (&$calls) {
            $calls['ships']++;

            return [2 => new HtmlString('<strong>Ships tomorrow</strong>')];
        });

        $lines = ProductCardLines::for($this->products(1, 2, 3));

        $this->assertSame(['seller' => 1, 'ships' => 1], $calls);
        $this->assertSame([1, 2, 3], array_keys($lines));
        $this->assertSame('<div class="card__ext-line" data-ext="seller">Sold by Demo Shop</div>', $lines[1]->toHtml());
        $this->assertSame(
            '<div class="card__ext-line" data-ext="seller">Sold by Demo Shop</div><div class="card__ext-line" data-ext="ships"><strong>Ships tomorrow</strong></div>',
            $lines[2]->toHtml()
        );
    }

    public function test_a_string_is_escaped_and_an_htmlable_is_printed_as_given(): void
    {
        ProductCardLines::add('text', fn () => [1 => '<script>alert(1)</script> & Co']);
        ProductCardLines::add('markup', fn () => [1 => new HtmlString('<em>Demo</em>')]);

        $html = ProductCardLines::for($this->products(1))[1]->toHtml();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; Co', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<em>Demo</em>', $html);
    }

    public function test_products_not_in_the_list_empty_fragments_and_other_answers_are_dropped(): void
    {
        ProductCardLines::add('odd', fn () => [1 => '', 2 => 42, 99 => 'Not in the list', 'x' => 'Not an id', 3 => 'Kept']);
        ProductCardLines::add('nonsense', fn () => 'not a list');

        $lines = ProductCardLines::for($this->products(1, 2, 3));

        $this->assertSame([3], array_keys($lines));
        $this->assertSame('<div class="card__ext-line" data-ext="odd">Kept</div>', $lines[3]->toHtml());
    }

    public function test_a_line_that_throws_is_reported_and_skipped(): void
    {
        Exceptions::fake();

        ProductCardLines::add('broken', fn () => throw new \RuntimeException('The seller list is unavailable.'));
        ProductCardLines::add('seller', fn () => [1 => 'Sold by Demo Shop']);

        $lines = ProductCardLines::for($this->products(1));

        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'The seller list is unavailable.');
        $this->assertSame('<div class="card__ext-line" data-ext="seller">Sold by Demo Shop</div>', $lines[1]->toHtml());
    }

    public function test_a_bad_or_taken_name_is_refused_and_logged(): void
    {
        Log::spy();

        ProductCardLines::add('Bad Name', fn () => [1 => 'One']);
        ProductCardLines::add('seller', fn () => [1 => 'First']);
        ProductCardLines::add('seller', fn () => [1 => 'Second']);

        $this->assertSame('<div class="card__ext-line" data-ext="seller">First</div>', ProductCardLines::for($this->products(1))[1]->toHtml());
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_a_paginator_and_an_array_are_read_like_a_collection(): void
    {
        ProductCardLines::add('seller', fn (Collection $products) => $products->mapWithKeys(fn (Product $p) => [$p->id => 'Sold'])->all());

        $page = new \Illuminate\Pagination\LengthAwarePaginator($this->products(4, 5)->all(), 10, 2);

        $this->assertSame([4, 5], array_keys(ProductCardLines::for($page)));
        $this->assertSame([6], array_keys(ProductCardLines::for($this->products(6)->all())));
        $this->assertSame([], ProductCardLines::for([]));
    }
}
