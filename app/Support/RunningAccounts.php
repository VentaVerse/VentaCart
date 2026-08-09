<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Support\Carbon;

/**
 * What has actually been selling, over a stated period.
 *
 * The pulse above this answers "is the business alright". These are the
 * running accounts underneath that answer: which goods carried the money,
 * how buyers paid, and whether the trade came from people who had bought
 * before. Everything is a count or a sum of what the store recorded. There
 * is no conversion rate, no sessions, no channel attribution and no
 * forecast, because Venta collects none of that and a plausible-looking
 * placeholder is worse than an absent figure.
 *
 * PLATFORM NEUTRALITY. Nothing here knows what a Venta store sells or how
 * it takes money. The payment mix reports whatever methods the orders
 * carry, in whatever order the money puts them; the customer-group split
 * is withheld unless the store genuinely runs more than one group carrying
 * trade in the period, which most stores do not.
 *
 * COST. Five queries, each bounded by the period rather than by the table.
 * The four that read orders seek on orders_store_created_money_index; the
 * product queries join order_products on its own foreign-key index, driven
 * by the small set of orders the period contains.
 */
class RunningAccounts
{
    /** How many products each ranking names. */
    private const RANK = 5;

    /** How many payment methods are listed before the tail is rolled up. */
    private const METHODS = 5;

    public static function for(int $storeId, Carbon $from, Carbon $to): array
    {
        $payments = self::payments($storeId, $from, $to);
        $revenue = array_sum(array_column($payments, 'revenue'));
        $orders = array_sum(array_column($payments, 'orders'));

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'byRevenue' => self::products($storeId, $from, $to, 'revenue'),
            'byUnits' => self::products($storeId, $from, $to, 'units'),
            'payments' => self::withShare(self::fold($payments, self::METHODS, 'Other methods'), $revenue),
            'buyers' => self::buyers($storeId, $from, $to),
            'groups' => self::groups($storeId, $from, $to),
        ];
    }

    /**
     * The period's best sellers, ranked by one of its two measures.
     *
     * Ranked by REVENUE and by UNITS separately and deliberately: the item
     * that carries the money and the item that moves are rarely the same
     * item, and a shop that only watches one of them misreads its own
     * catalogue. Both are returned so the sheet can set them side by side.
     *
     * Grouping is on the snapshot NAME as well as the product id, so a
     * product deleted since the sale still names itself rather than
     * collapsing into a nameless row, and a renamed product does not
     * retroactively rewrite what an old order said it sold.
     *
     * @return array<int, array{name:string, productId:?int, revenue:float, units:int, weight:float}>
     */
    private static function products(int $storeId, Carbon $from, Carbon $to, string $rank): array
    {
        $live = Revenue::condition($storeId, 'orders');

        $rows = OrderProduct::query()
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.created_at', '>=', $from)
            ->where('orders.created_at', '<', $to)
            ->whereRaw($live)
            ->groupBy('order_products.product_id', 'order_products.name')
            ->selectRaw(
                'order_products.product_id AS product_id, order_products.name AS name, '
                . 'SUM(order_products.total) AS revenue, SUM(order_products.quantity) AS units'
            )
            ->orderByDesc($rank === 'units' ? 'units' : 'revenue')
            ->limit(self::RANK)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'name' => (string) ($row->name ?: 'Unnamed item'),
                'productId' => $row->product_id ? (int) $row->product_id : null,
                'revenue' => (float) $row->revenue,
                'units' => (int) $row->units,
                'weight' => 0.0,
            ];
        }

        // The bar beside each row is drawn against the leader of its own
        // ranking, not against the period's total. It is a shape that
        // repeats the number beside it, never a claim about share.
        $top = $out === [] ? 0.0 : max(array_column($out, $rank === 'units' ? 'units' : 'revenue'));

        foreach ($out as $i => $row) {
            $value = (float) ($rank === 'units' ? $row['units'] : $row['revenue']);
            $out[$i]['weight'] = $top > 0 ? round($value / $top, 4) : 0.0;
        }

        return $out;
    }

    /**
     * How the period's money arrived.
     *
     * The label is the title the ORDER recorded, because that is what the
     * merchant called the method on the day it was used; renaming a method
     * today does not rewrite what last month's orders were paid with. Orders
     * carrying no method at all are a real category on a migrated store and
     * are named rather than dropped.
     *
     * @return array<int, array{key:string, label:string, orders:int, revenue:float}>
     */
    private static function payments(int $storeId, Carbon $from, Carbon $to): array
    {
        $rows = Revenue::applyTo(
            Order::query()
                ->where('store_id', $storeId)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to),
            $storeId
        )
            ->groupBy('payment_method', 'payment_method_title')
            ->selectRaw('payment_method, payment_method_title, COUNT(*) AS orders, SUM(total) AS revenue')
            ->get();

        // Two rows can share a label (one method, retitled mid period), so
        // the fold happens here rather than being left to the view.
        $merged = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row->payment_method_title ?: $row->payment_method));
            $label = $label === '' ? 'Not recorded' : $label;
            $key = $label;

            $merged[$key] ??= ['key' => $key, 'label' => $label, 'orders' => 0, 'revenue' => 0.0];
            $merged[$key]['orders'] += (int) $row->orders;
            $merged[$key]['revenue'] += (float) $row->revenue;
        }

        $merged = array_values($merged);
        usort($merged, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $merged;
    }

    /**
     * New buyers against returning ones.
     *
     * A BUYER IS AN EMAIL ADDRESS, which is the only identity a Venta
     * checkout always captures: registered customers and guests both supply
     * one, and the same person ordering twice as a guest is correctly one
     * buyer. Matching on the customer account instead would call every guest
     * order a new buyer forever.
     *
     * Returning means "this address placed an order before this period
     * started", asked as an EXISTS so the database stops at the first row it
     * finds rather than counting a decade of them.
     *
     * @return array{total:int, new:int, returning:int}
     */
    private static function buyers(int $storeId, Carbon $from, Carbon $to): array
    {
        // ONE statement, and deliberately not two. Pulling the period's
        // addresses into PHP and asking the second question with an IN list
        // works on a quiet month and falls over on a busy one, where the
        // list is every buyer the store had. Kept in SQL, the cost is one
        // index seek per distinct buyer and nothing is transferred.
        $row = Order::query()->getConnection()->selectOne(
            'SELECT COUNT(*) AS total, '
            . 'SUM(CASE WHEN EXISTS ('
            . '  SELECT 1 FROM orders b'
            . '  WHERE b.store_id = ? AND b.email = p.email'
            . '    AND b.created_at < ? AND b.order_status_id IS NOT NULL'
            . ') THEN 1 ELSE 0 END) AS returning_buyers '
            . 'FROM (SELECT DISTINCT email FROM orders'
            . '      WHERE store_id = ? AND created_at >= ? AND created_at < ?'
            . "        AND order_status_id IS NOT NULL AND email IS NOT NULL AND email <> '') p",
            [$storeId, $from, $storeId, $from, $to]
        );

        $total = (int) ($row->total ?? 0);
        $returning = (int) ($row->returning_buyers ?? 0);

        return [
            'total' => $total,
            'new' => max($total - $returning, 0),
            'returning' => min($returning, $total),
        ];
    }

    /**
     * Revenue split by customer group, or nothing at all.
     *
     * MOST STORES HAVE ONE GROUP, and a split of one is not a split. This
     * returns an empty array unless at least two groups carried trade in the
     * period, so the line appears on the stores where it means something and
     * is absent everywhere else rather than printing a single 100% row.
     *
     * Guest orders belong to no group and are not invented into one; the
     * split is explicitly of the trade that came from customer accounts.
     *
     * @return array<int, array{name:string, orders:int, revenue:float, share:float}>
     */
    private static function groups(int $storeId, Carbon $from, Carbon $to): array
    {
        $rows = Revenue::applyTo(
            Order::query()
                ->join('customers', 'customers.id', '=', 'orders.customer_id')
                ->join('customer_groups', 'customer_groups.id', '=', 'customers.customer_group_id')
                ->where('orders.store_id', $storeId)
                ->where('orders.created_at', '>=', $from)
                ->where('orders.created_at', '<', $to),
            $storeId
        )
            ->groupBy('customer_groups.id', 'customer_groups.name')
            ->selectRaw('customer_groups.name AS name, COUNT(*) AS orders, SUM(orders.total) AS revenue')
            ->orderByDesc('revenue')
            ->get();

        if ($rows->count() < 2) {
            return [];
        }

        $split = [];
        foreach ($rows as $row) {
            $split[] = [
                'key' => (string) $row->name,
                'label' => (string) $row->name,
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ];
        }

        return self::withShare($split, array_sum(array_column($split, 'revenue')));
    }

    /**
     * Keep the leaders, roll the tail into one named row.
     *
     * @param  array<int, array{key:string, label:string, orders:int, revenue:float}>  $rows
     * @return array<int, array{key:string, label:string, orders:int, revenue:float}>
     */
    private static function fold(array $rows, int $keep, string $tailLabel): array
    {
        if (count($rows) <= $keep + 1) {
            return $rows;
        }

        $head = array_slice($rows, 0, $keep);
        $tail = array_slice($rows, $keep);

        $head[] = [
            'key' => 'other',
            'label' => $tailLabel,
            'orders' => array_sum(array_column($tail, 'orders')),
            'revenue' => array_sum(array_column($tail, 'revenue')),
        ];

        return $head;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function withShare(array $rows, float $total): array
    {
        foreach ($rows as $i => $row) {
            $rows[$i]['share'] = $total > 0 ? ((float) $row['revenue']) / $total : 0.0;
        }

        return $rows;
    }
}
