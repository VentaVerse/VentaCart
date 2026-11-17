<?php

namespace Themes\Xelga\Sections;

use App\Models\Brand;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * Brand logos from core's brands: every active brand in its sort order, or
 * the ones the merchant chose, each with its own logo and page unless the
 * merchant set another. A brand that is switched off is not shown.
 */
final class BrandLogos implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $canLink = Route::has('brand.show');
        $items = [];

        if (($settings['source'] ?? 'chosen') === 'all') {
            $brands = Brand::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')
                ->limit(max(1, min(48, (int) ($settings['count'] ?? 12))))->get(['id', 'name', 'slug', 'image']);
            foreach ($brands as $brand) {
                $items[] = [
                    'block' => null,
                    'name' => (string) $brand->name,
                    'picture' => $brand->image ? Picture::of($brand->image) : null,
                    'url' => $canLink ? route('brand.show', $brand->slug) : null,
                ];
            }

            return ['items' => $items, 'from_blocks' => false];
        }

        $ids = array_values(array_filter(array_map(fn (array $b) => ProductSource::id($b['settings']['brand'] ?? null), $blocks)));
        $brands = $ids === [] ? collect() : Brand::query()->whereIn('id', $ids)->where('is_active', true)->get(['id', 'name', 'slug', 'image'])->keyBy('id');

        foreach ($blocks as $block) {
            $s = $block['settings'];
            $brandId = ProductSource::id($s['brand'] ?? null);
            $brand = $brandId ? $brands->get($brandId) : null;
            // A chosen brand that is gone or switched off takes its tile with it.
            if ($brandId !== null && $brand === null && ! $context->preview) {
                continue;
            }
            $name = trim((string) ($s['name'] ?? ''));
            $name = $name !== '' ? $name : (string) ($brand?->name ?? '');
            if ($name === '' && ! $context->preview) {
                continue;
            }
            $items[] = [
                'block' => $block,
                'name' => $name,
                'picture' => Picture::of($s['image'] ?? '') ?? ($brand?->image ? Picture::of($brand->image) : null),
                'url' => LinkValue::resolve($s['link'] ?? null) ?? ($brand && $canLink ? route('brand.show', $brand->slug) : null),
            ];
        }

        return ['items' => $items, 'from_blocks' => true];
    }
}
