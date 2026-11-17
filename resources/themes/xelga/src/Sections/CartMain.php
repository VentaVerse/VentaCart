<?php

namespace Themes\Xelga\Sections;

use App\Models\CartItem;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * The cart page: core's cart lines with the prices core stored on them, the
 * lines core flags as out of stock or no longer for sale, the subtotal core
 * worked out, and what the summary says around them.
 */
final class CartMain implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $items = collect($context->get('items', []))->filter(fn ($i) => $i instanceof CartItem)->values();
        $subtotal = (float) $context->get('subtotal', 0);
        $outOfStock = array_map('intval', (array) $context->get('outOfStockIds', []));
        $unavailable = array_map('intval', (array) $context->get('unavailableIds', []));
        $freeFrom = $settings['show_free_shipping'] ? CartSummary::freeShippingFrom() : null;
        $coupon = $settings['show_coupon'] ? CartSummary::coupon($subtotal) : null;

        $lines = $items->map(fn (CartItem $item) => self::line($item, $outOfStock, $unavailable))->all();

        return [
            'lines' => $lines,
            'groups' => self::groups($context, $items, $lines),
            'count' => (int) $items->sum('quantity'),
            'subtotal' => $subtotal,
            'blocked' => $outOfStock !== [] || $unavailable !== [],
            'has_unavailable' => $unavailable !== [],
            'has_out_of_stock' => $outOfStock !== [],
            'free_from' => $freeFrom,
            'coupon' => $coupon,
            'coupons_on' => $settings['show_coupon'] && CartSummary::couponsOn(),
            'signed_in' => CartSummary::signedIn(),
            'cod' => CartSummary::codOn(),
            'payments' => $settings['show_payment_icons'] ? CartSummary::payments() : [],
            'checkout' => CartSummary::checkoutUrl(),
            'login' => Route::has('customer.login') ? route('customer.login') : null,
            'estimate' => $settings['show_estimate'] && Route::has('cart.estimate-shipping'),
            'empty_link' => LinkValue::resolve($settings['empty_link'] ?? null) ?? route('home'),
            'endpoints' => CartSummary::endpoints(),
        ];
    }

    /**
     * The lines under the groups an extension made of them (one per
     * seller, say), as core checked them, or null for one plain list.
     * Core puts the lines no group took first; they get a neutral title
     * ("Other items") when named groups follow, so they never read as part
     * of a seller's group. Alone, they are the plain list.
     *
     * @param  \Illuminate\Support\Collection<int, CartItem>  $items
     * @param  list<array>  $lines
     * @return list<array{key: string, label: string, url: ?string, shipping: ?array, lines: list<array>}>|null
     */
    private static function groups(RenderContext $context, $items, array $lines): ?array
    {
        if (! class_exists(\App\Support\CartGroups::class)) {
            return null;
        }
        $groups = array_key_exists('cartGroups', $context->page) ? $context->get('cartGroups') : \App\Support\CartGroups::for($items);
        if (! is_array($groups) || $groups === []) {
            return null;
        }

        $byId = array_column($lines, null, 'id');
        $out = [];
        foreach ($groups as $group) {
            $groupLines = array_values(array_filter(array_map(fn (int $id) => $byId[$id] ?? null, (array) $group['lines'])));
            if ($groupLines === []) {
                continue;
            }
            $shipping = $group['shipping'] ?? null;
            $rest = $group['key'] === \App\Support\CartGroups::REST;
            $out[] = [
                'key' => (string) $group['key'],
                'label' => (string) $group['label'],
                'title' => $rest ? (count($groups) > 1 ? 'Other items' : '') : (string) $group['label'],
                'url' => $group['url'] ?? null,
                'shipping' => is_array($shipping) ? [
                    'label' => (string) $shipping['label'],
                    'amount' => $shipping['amount'] === null ? null : money((float) $shipping['amount']),
                    'note' => $shipping['note'] ?? null,
                ] : null,
                'lines' => $groupLines,
            ];
        }

        $onlyRest = count($out) === 1 && $out[0]['key'] === \App\Support\CartGroups::REST;

        return $out === [] || $onlyRest ? null : $out;
    }

    /** One line as the page draws it. */
    private static function line(CartItem $item, array $outOfStock, array $unavailable): array
    {
        $product = $item->product;
        $stock = null;
        if ($product && $product->track_stock) {
            $stock = (int) ($item->productVariant ? $item->productVariant->quantity : $product->quantity);
        }

        return [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'variant' => (string) ($item->variant_label ?? ''),
            'url' => $product && $product->slug && Route::has('product.show') ? route('product.show', $product->slug) : null,
            'image' => $item->image ? storage_url($item->image) : null,
            'price' => money($item->price),
            'total' => money((float) $item->price * (int) $item->quantity),
            'quantity' => (int) $item->quantity,
            'max' => $stock !== null && $stock > 0 ? $stock : null,
            'out_of_stock' => in_array((int) $item->id, $outOfStock, true),
            'unavailable' => in_array((int) $item->id, $unavailable, true),
        ];
    }
}
