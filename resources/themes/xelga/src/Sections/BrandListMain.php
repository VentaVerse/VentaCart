<?php

namespace Themes\Xelga\Sections;

use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * The brands page: every brand BrandController lists (active, with at
 * least one product on sale in the store), each with its logo, name and
 * product count, linking to its own page.
 */
final class BrandListMain implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $canLink = Route::has('brand.show');
        $items = [];
        foreach ($context->get('brands', []) as $brand) {
            $count = (int) ($brand->products_count ?? 0);
            $items[] = [
                'name' => (string) $brand->name,
                'url' => $canLink ? route('brand.show', $brand->slug) : null,
                'picture' => $brand->image ? Picture::of($brand->image) : null,
                'count' => $count . ' ' . ($count === 1 ? 'product' : 'products'),
            ];
        }

        return ['items' => $items];
    }
}
