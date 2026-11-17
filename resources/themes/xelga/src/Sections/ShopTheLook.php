<?php

namespace Themes\Xelga\Sections;

use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * Shop the look: a photo with a dot on each product in it. The products
 * come through core's feed in the dots' order; a dot whose product the
 * shopper cannot see is left out.
 */
final class ShopTheLook implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            if (($id = ProductSource::id($block['settings']['product'] ?? null)) !== null) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        $products = $ids === [] ? [] : ProductSource::get(['ids' => $ids, 'limit' => \App\Support\ProductFeed::LIMIT_MAX]);

        $cards = [];
        foreach (ProductCards::for($products, Swatches::wanted($context)) as $card) {
            $cards[$card['id']] = $card;
        }

        $spots = [];
        foreach ($blocks as $block) {
            $id = ProductSource::id($block['settings']['product'] ?? null);
            $spots[$block['id']] = $id !== null ? ($cards[$id] ?? null) : null;
        }

        return [
            'picture' => Picture::of($settings['image'] ?? ''),
            'spots' => $spots,
        ];
    }
}
