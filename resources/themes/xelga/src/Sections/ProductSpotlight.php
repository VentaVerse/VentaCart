<?php

namespace Themes\Xelga\Sections;

use App\Support\ProductAttributes;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * One product with its price, card details and, while its special price
 * runs, a countdown to the moment core ends it. The product comes through
 * core's feed, so a product hidden from the shopper's group is not shown.
 */
final class ProductSpotlight implements SectionData
{
    /** A countdown further away than this is not a deadline worth showing. */
    private const COUNTDOWN_MAX_DAYS = 366;

    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $id = ProductSource::id($settings['product'] ?? null);
        $product = $id === null ? null : ProductSource::get(['ids' => [$id], 'limit' => 1])->first();
        $card = $product ? (ProductCards::for([$product], Swatches::wanted($context), lines: false)[0] ?? null) : null;

        $endsAt = null;
        if ($card && $card['on_sale'] && $card['ends_at'] !== null) {
            $end = $card['ends_at'];
            if ($end->isFuture() && $end->lessThanOrEqualTo(now()->addDays(self::COUNTDOWN_MAX_DAYS))) {
                $endsAt = $end->toIso8601String();
            }
        }

        return [
            'card' => $card,
            'specs' => $product ? array_slice(ProductAttributes::card($product), 0, 4) : [],
            'picture' => Picture::of($settings['image'] ?? '') ?? ($card['image'] ? Picture::of($card['image']['path']) : null),
            'ends_at' => $endsAt,
            'empty' => $id === null ? 'Pick the product to show.' : 'This product is switched off or hidden from guests, so shoppers do not see this section.',
        ];
    }
}
