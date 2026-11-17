<?php

namespace Themes\Xelga\Sections;

use App\Support\RichText;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * The brand page's listing: the brand's logo, text and product count, and
 * the page of products BrandController chose with the filters it accepts
 * (price, availability, on sale, tags). Everything shown is core's.
 */
final class BrandMain implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $brand = $context->get('brand');

        return [
            'title' => (string) ($brand?->name ?? ''),
            // Core does not clean brand text on save, so it is cleaned here before it is printed.
            'description' => RichText::forDisplay($brand?->description),
            'picture' => $brand?->image ? Picture::of($brand->image) : null,
            'list' => Listing::page($context->get('products'), $context),
            'sorts' => Listing::sorts(Listing::SORTS, (string) $context->get('sort', 'date_added')),
            'filters' => Listing::filters([
                'price' => $context->get('priceRange'),
                'tags' => $context->get('availableTags', []),
            ]),
            'carried' => Listing::carried(['sort']),
            'per_page' => (int) ($context->get('products')?->perPage() ?? 0),
            'settings_url' => Listing::settingsUrl(),
        ];
    }
}
