<?php

namespace Themes\Xelga\Sections;

use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * Featured products: a list from core's product feed (newest, best sellers,
 * on sale, most viewed, a category, a brand or picked by hand), drawn with
 * the shared product card. What a shopper may see and pay is core's call.
 */
final class FeaturedProducts implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $count = (int) ($settings['count'] ?? 8);
        $criteria = ProductSource::criteriaFor($settings, $count);
        $products = $criteria === null ? [] : ProductSource::get($criteria);

        return [
            'cards' => ProductCards::for($products, Swatches::wanted($context)),
            'empty' => $this->emptyNote($settings, $criteria === null),
            'link' => LinkValue::resolve($settings['link'] ?? null),
        ];
    }

    /** What the editor tells the merchant when the list comes out empty. */
    private function emptyNote(array $settings, bool $nothingChosen): string
    {
        return match (true) {
            $nothingChosen && ($settings['source'] ?? '') === 'category' => 'Pick a category for this section.',
            $nothingChosen && ($settings['source'] ?? '') === 'brand' => 'Pick a brand for this section.',
            $nothingChosen => 'Pick the products for this section.',
            ($settings['source'] ?? '') === 'on-sale' => 'No product is on sale right now, so shoppers do not see this section.',
            default => 'No products match yet, so shoppers do not see this section. Products hidden from guests do not show in the preview.',
        };
    }
}
