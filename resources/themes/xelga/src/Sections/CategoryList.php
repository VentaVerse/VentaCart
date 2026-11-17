<?php

namespace Themes\Xelga\Sections;

use App\Support\CategoryTree;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * A list of the store's categories, as core orders them: the top ones,
 * and with them the ones directly below when the merchant asks for two
 * levels. Only active categories are listed, and the category the
 * shopper is looking at is marked.
 */
final class CategoryList implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $deepest = ($settings['depth'] ?? 'two') === 'top' ? 0 : 1;
        $current = $context->get('category');
        $currentId = is_object($current) && isset($current->id) ? (int) $current->id : null;
        $canLink = Route::has('category.show');

        $items = [];
        // The full path of the last category met at each depth: core gives
        // paths ("Laptops > Gaming laptops"), the list shows own names.
        $paths = [];
        $parentListed = false;
        foreach (CategoryTree::options(activeOnly: true) as $option) {
            $depth = (int) $option['depth'];
            $paths[$depth] = (string) $option['name'];
            $listed = $depth <= $deepest && $canLink && (string) $option['slug'] !== '';
            if ($depth === 0) {
                $parentListed = $listed;
            }
            if (! $listed) {
                continue;
            }
            $parentPath = $depth > 0 ? ($paths[$depth - 1] ?? '') . CategoryTree::SEPARATOR : '';
            $entry = [
                'name' => str_starts_with($paths[$depth], $parentPath) ? substr($paths[$depth], strlen($parentPath)) : $paths[$depth],
                'url' => route('category.show', $option['slug']),
                'current' => $option['id'] === $currentId,
                'children' => [],
            ];
            if ($depth === 0) {
                $items[] = $entry;
            } elseif ($parentListed) {
                // Core lists a category right after its parent, so the parent is the last one added.
                $items[array_key_last($items)]['children'][] = $entry;
            }
        }

        return ['items' => $items];
    }
}
