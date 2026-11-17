<?php

namespace App\Support;

use App\Services\ExtensionManager;

/**
 * The core's one question about the reviews extension: is it here? The
 * storefront asks before eager-loading rating aggregates or rendering
 * review sections, so a store without the extension simply shows no
 * stars rather than erroring.
 *
 * This asks the REGISTRY, not just class_exists(). ExtensionManager's own
 * docblock spells out why: spl_autoload_register is process-global, so a class
 * loaded once stays defined for the rest of the process even after the
 * extension is disabled or uninstalled, and "anything that uses class_exists()
 * on an extension's classes as a proxy for 'currently installed' must pair it
 * with a real state check". A single-request web process happens to be safe by
 * accident; a long-lived one (Octane, the test suite) is not, and this helper
 * decides whether customer-facing markup renders.
 *
 * class_exists() is still checked first and still matters: the registry can say
 * enabled while the files are gone (state 'missing_files'), and callers go on to
 * touch the model.
 */
class Reviews
{
    /** Reviews drawn with a product page, and fetched per "Show more". */
    public const PAGE_SIZE = 10;

    /** The most reviews one page may ask for. */
    public const PAGE_SIZE_MAX = 50;

    /**
     * One page of a product's approved reviews, newest first, with photos.
     *
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public static function page(\App\Models\Product $product, int $page = 1, int $perPage = self::PAGE_SIZE): \Illuminate\Contracts\Pagination\Paginator
    {
        return $product->approvedReviews()
            ->with('images')
            ->orderByDesc('id')
            ->simplePaginate(max(1, min(self::PAGE_SIZE_MAX, $perPage)), ['*'], 'page', max(1, $page));
    }

    /**
     * How many approved reviews the product has and their average rating,
     * from one aggregate query rather than by loading every review.
     *
     * @return array{count: int, average: float}
     */
    public static function summary(\App\Models\Product $product): array
    {
        $row = $product->approvedReviews()->reorder()->toBase()
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as review_average')
            ->first();

        return ['count' => (int) ($row->review_count ?? 0), 'average' => (float) ($row->review_average ?? 0)];
    }

    public static function enabled(): bool
    {
        if (! class_exists(\Extensions\Reviews\Models\Review::class)) {
            return false;
        }

        return app(ExtensionManager::class)->state('reviews') === 'enabled';
    }
}
