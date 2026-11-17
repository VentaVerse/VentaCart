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
