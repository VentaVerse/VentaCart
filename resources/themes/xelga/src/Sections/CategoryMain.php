<?php

namespace Themes\Xelga\Sections;

use App\Support\RichText;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * The category page's listing: the category's name, text and photo, its
 * subcategories, and the page of products CategoryController chose with
 * the filters and sort it accepts. Every product, price and count is
 * core's; this class only arranges what the controller handed the page.
 */
final class CategoryMain implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $category = $context->get('category');
        $canLink = Route::has('category.show');

        $subcategories = [];
        foreach ($context->get('subcategories', []) as $child) {
            $subcategories[] = [
                'name' => (string) $child->name,
                'url' => $canLink ? route('category.show', $child->slug) : '#',
                'picture' => $child->image ? Picture::of($child->image) : null,
            ];
        }

        return [
            'title' => (string) ($category?->name ?? ''),
            // Category text is printed as HTML, so it is cleaned again here rather than trusted as stored.
            'description' => RichText::forDisplay($category?->description),
            'picture' => $category?->image ? Picture::of($category->image) : null,
            'subcategories' => $subcategories,
            'list' => Listing::page($context->get('products'), $context),
            'sorts' => Listing::sorts(Listing::SORTS, (string) $context->get('sort', 'date_added')),
            'filters' => Listing::filters(self::filterSource($context)),
            'carried' => Listing::carried(['sort']),
            // A Filters section elsewhere on the page holds the filter form; the listing then links to it.
            'filters_at' => $context->has('filters') ? $context->idOf('filters') : null,
            'per_page' => (int) ($context->get('products')?->perPage() ?? 0),
            'settings_url' => Listing::settingsUrl(),
        ];
    }

    /**
     * The filters core's category controller worked out for this page:
     * the price range, brands, tags and attributes of the products it
     * lists. The listing and the Filters section read the same ones.
     *
     * @return array{price: mixed, brands: iterable, brand_filter: bool, tags: iterable, attributes: mixed}
     */
    public static function filterSource(RenderContext $context): array
    {
        return [
            'price' => $context->get('priceRange'),
            'brands' => $context->get('availableBrands', []),
            'brand_filter' => true,
            'tags' => $context->get('availableTags', []),
            'attributes' => $context->get('availableAttributes', []),
        ];
    }
}
