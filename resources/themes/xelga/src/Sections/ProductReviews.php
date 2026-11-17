<?php

namespace Themes\Xelga\Sections;

use App\Support\Reviews;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * Product reviews: the approved reviews core's product page loaded, with
 * the average and count it worked out. Nothing at all while the reviews
 * extension is off.
 */
final class ProductReviews implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $enabled = Reviews::enabled();
        $reviews = $enabled ? collect($context->get('approvedReviews', [])) : collect();

        return [
            'enabled' => $enabled,
            'reviews' => $reviews->sortByDesc(fn ($r) => $r->published_at ?? $r->created_at)->values(),
            'average' => round((float) $context->get('averageRating', 0), 1),
            'count' => $reviews->count(),
        ];
    }
}
