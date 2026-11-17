<?php

namespace Themes\Xelga\Sections;

use App\Models\Product;
use App\Support\Reviews;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * Product reviews: the count and average core worked out, and the first
 * page of approved reviews core loaded. The rest are fetched a page at a
 * time from core's product.reviews endpoint when the shopper asks, so a
 * product with hundreds of reviews does not send them all with the page.
 * Nothing at all while the reviews extension is off.
 */
final class ProductReviews implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $enabled = Reviews::enabled();
        $product = $context->subject;
        if (! $enabled || ! $product instanceof Product) {
            return ['enabled' => $enabled, 'reviews' => collect(), 'average' => 0.0, 'count' => 0, 'more_url' => null];
        }

        $perPage = max(1, min(Reviews::PAGE_SIZE_MAX, (int) ($settings['per_page'] ?? Reviews::PAGE_SIZE)));
        $count = (int) $context->get('reviewCount', 0);
        $reviews = collect($context->get('reviewsFirstPage', []));
        $moreUrl = $context->get('reviewsMoreUrl');

        // The section shows more at first than core's page holds: read a
        // first page of this section's own size instead.
        if ($perPage > $reviews->count() && $moreUrl !== null) {
            $page = Reviews::page($product, 1, $perPage);
            $reviews = collect($page->items());
            $moreUrl = $page->hasMorePages() ? route('product.reviews', ['product' => $product->slug, 'page' => 2, 'per_page' => $perPage]) : null;
        }

        return [
            'enabled' => true,
            'reviews' => $reviews->values(),
            'average' => round((float) $context->get('averageRating', 0), 1),
            'count' => $count,
            'more_url' => $moreUrl,
        ];
    }
}
