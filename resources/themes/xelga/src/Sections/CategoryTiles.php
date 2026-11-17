<?php

namespace Themes\Xelga\Sections;

use App\Models\Category;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\Picture;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * Category tiles: each tile links to a core category and uses its name and
 * photo unless the merchant set their own. A category that is switched off
 * or deleted drops its tile for shoppers.
 */
final class CategoryTiles implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $ids = array_values(array_filter(array_map(fn (array $b) => ProductSource::id($b['settings']['category'] ?? null), $blocks)));
        $categories = $ids === [] ? collect() : Category::query()->whereIn('id', $ids)->where('is_active', true)->get(['id', 'name', 'slug', 'image'])->keyBy('id');
        $canLink = Route::has('category.show');

        $tiles = [];
        foreach ($blocks as $block) {
            $s = $block['settings'];
            $category = ($id = ProductSource::id($s['category'] ?? null)) ? $categories->get($id) : null;
            $label = trim((string) ($s['label'] ?? ''));
            $tiles[$block['id']] = [
                'category' => $category !== null,
                'label' => $label !== '' ? $label : (string) ($category?->name ?? ''),
                'url' => $category && $canLink ? route('category.show', $category->slug) : null,
                'picture' => Picture::of($s['image'] ?? '') ?? ($category?->image ? Picture::of($category->image) : null),
            ];
        }

        return [
            'cols' => $context->fitColumns($settings['cols'] ?? 4),
            'tiles' => $tiles,
            'link' => LinkValue::resolve($settings['link'] ?? null),
        ];
    }
}
