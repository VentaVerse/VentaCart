<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Fragments extensions add to the customer's order pages, in fixed places
 * a theme draws: under a row of the orders list, in an order's summary and
 * among an order's actions ("Return an item", "Contact the seller").
 *
 * A fragment is HTML the extension wrote: an Htmlable or a string the
 * extension has already escaped. Core prints it as given, so an extension
 * escapes every value it puts in one, as any Blade view of its own does.
 *
 * For the list, a callable receives the page's orders once, so it can read
 * what it needs in one query, and answers fragments keyed by order id. For
 * an order's page, it receives the order. A callable that throws is
 * reported and left out; the page still draws.
 */
final class OrderPanels
{
    /** Under one row of the customer's orders list. */
    public const ROW_AFTER = 'order.row.after';

    /** In the summary of the customer's order page. */
    public const DETAIL_SUMMARY = 'order.detail.summary';

    /** With the actions of the customer's order page. */
    public const DETAIL_ACTIONS = 'order.detail.actions';

    public const SLOTS = [self::ROW_AFTER, self::DETAIL_SUMMARY, self::DETAIL_ACTIONS];

    /** @var array<string, list<callable>> */
    private static array $panels = [];

    /**
     * From an extension's service provider.
     *
     * @param  callable(Collection<int, Order>): array<int, Htmlable|string>|callable(Order): (Htmlable|string|null)  $fn
     */
    public static function add(string $slot, callable $fn): void
    {
        if (! in_array($slot, self::SLOTS, true)) {
            Log::warning('An order panel was refused: unknown slot.', ['slot' => $slot]);

            return;
        }

        self::$panels[$slot][] = $fn;
    }

    public static function has(string $slot): bool
    {
        return (self::$panels[$slot] ?? []) !== [];
    }

    public static function forget(): void
    {
        self::$panels = [];
    }

    /**
     * The fragments under each row of a page of orders, by order id. Each
     * callable runs once for the whole page; only orders on the page are
     * kept.
     *
     * @param  iterable<Order>  $orders  the page's orders (a paginator or a collection)
     * @return array<int, HtmlString>
     */
    public static function rows(iterable $orders): array
    {
        if (! self::has(self::ROW_AFTER)) {
            return [];
        }

        $orders = $orders instanceof \Illuminate\Contracts\Pagination\Paginator ? collect($orders->items()) : collect($orders);
        $orders = $orders->filter(fn ($order) => $order instanceof Order)->values();
        if ($orders->isEmpty()) {
            return [];
        }
        $ids = array_flip($orders->map(fn (Order $order) => (int) $order->getKey())->all());

        $out = [];
        foreach (self::$panels[self::ROW_AFTER] as $fn) {
            try {
                $answer = $fn($orders);
            } catch (\Throwable $e) {
                report($e);
                continue;
            }
            if (! is_array($answer)) {
                continue;
            }
            foreach ($answer as $id => $fragment) {
                $html = self::html($fragment);
                if ($html !== '' && is_int($id) && isset($ids[$id])) {
                    $out[$id] = ($out[$id] ?? '') . $html;
                }
            }
        }

        return array_map(fn (string $html) => new HtmlString($html), $out);
    }

    /** The fragments of one slot of an order's page, joined in the order they were added. */
    public static function render(string $slot, Order $order): HtmlString
    {
        if ($slot === self::ROW_AFTER || ! self::has($slot)) {
            return new HtmlString('');
        }

        $html = '';
        foreach (self::$panels[$slot] as $fn) {
            try {
                $html .= self::html($fn($order));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return new HtmlString($html);
    }

    private static function html(mixed $fragment): string
    {
        return match (true) {
            $fragment instanceof Htmlable => (string) $fragment->toHtml(),
            is_string($fragment) => $fragment,
            default => '',
        };
    }
}
