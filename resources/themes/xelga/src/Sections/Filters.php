<?php

namespace Themes\Xelga\Sections;

use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * The category page's filter form, drawn in a section of its own so it can
 * sit in a page side. The filters are the ones core's category controller
 * offers for the products it lists, exactly as the listing would show
 * them; the listing leaves its own filter column out while this section
 * is drawn.
 */
final class Filters implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        return [
            'filters' => Listing::filters(CategoryMain::filterSource($context)),
            'carried' => Listing::carried(['sort']),
        ];
    }
}
