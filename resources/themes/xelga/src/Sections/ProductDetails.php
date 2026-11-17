<?php

namespace Themes\Xelga\Sections;

use App\Models\Product;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * Product details: the description and the attribute groups exactly as
 * core's product page hands them over, and the merchant's own text panels.
 * A panel with nothing to show is left out.
 */
final class ProductDetails implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $product = $context->get('product', $context->subject);
        $groups = $context->get('attributeGroups', []);

        $panels = [];
        foreach ($blocks as $block) {
            $title = trim((string) ($block['settings']['title'] ?? ''));
            $empty = match ($block['type']) {
                'description' => ! $product instanceof Product || trim(strip_tags((string) $product->description, '<img><iframe><video>')) === '',
                'specifications' => ! is_array($groups) || $groups === [],
                default => trim(strip_tags((string) ($block['settings']['body'] ?? ''))) === '',
            };
            if ($empty && ! $context->preview) {
                continue;
            }
            $panels[] = $block + [
                'title' => $title !== '' ? $title : (string) $block['label'],
                'empty' => $empty,
                'slug' => $block['id'],
            ];
        }

        return [
            'product' => $product instanceof Product ? $product : null,
            'groups' => is_array($groups) ? $groups : [],
            'panels' => $panels,
        ];
    }
}
