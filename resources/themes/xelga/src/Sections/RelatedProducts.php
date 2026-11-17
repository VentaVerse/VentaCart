<?php

namespace Themes\Xelga\Sections;

use App\Models\Product;
use App\Support\ProductFeed;
use Illuminate\Support\Collection;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * Related products: other products from the product's categories, the way
 * core's product page picks them, or from its brand. The list comes from
 * core (the page's own related list when core loaded one, else core's
 * product feed), so visibility per customer group always applies.
 */
final class RelatedProducts implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $product = $context->get('product', $context->subject);
        $count = max(2, min(12, (int) ($settings['count'] ?? 4)));
        if (! $product instanceof Product) {
            return ['cards' => [], 'empty' => 'Shown on product pages.'];
        }

        $products = ($settings['source'] ?? 'related') === 'brand'
            ? self::sameBrand($product, $context, $count)
            : self::related($product, $context, $count);

        return [
            'cards' => ProductCards::for($products, Swatches::wanted($context)),
            'empty' => ($settings['source'] ?? '') === 'brand'
                ? 'This product has no brand, or no other product of its brand, so shoppers do not see this section.'
                : 'No other product shares a category with this one, so shoppers do not see this section.',
        ];
    }

    private static function related(Product $product, RenderContext $context, int $count): Collection
    {
        $loaded = collect($context->get('relatedProducts', []));
        if ($loaded->isNotEmpty()) {
            return $loaded->take($count);
        }

        $category = $product->relationLoaded('categories') ? $product->categories->first() : $product->categories()->first();
        if ($category === null) {
            return collect();
        }

        return self::without($product, ProductFeed::get(['category' => $category->id, 'filter' => 'random', 'limit' => $count + 1]), $count);
    }

    private static function sameBrand(Product $product, RenderContext $context, int $count): Collection
    {
        $loaded = collect($context->get('sameBrandProducts', []));
        if ($loaded->isNotEmpty()) {
            return $loaded->take($count);
        }
        if (! $product->brand_id) {
            return collect();
        }

        return self::without($product, ProductFeed::get(['brand' => $product->brand_id, 'filter' => 'random', 'limit' => $count + 1]), $count);
    }

    private static function without(Product $product, iterable $products, int $count): Collection
    {
        return collect($products)->reject(fn ($p) => (int) $p->id === (int) $product->id)->take($count)->values();
    }
}
