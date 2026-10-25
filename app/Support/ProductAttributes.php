<?php

namespace App\Support;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Product attributes as themes draw them: the chips on a card, the groups
 * on a product page, and the filters on a category page.
 */
final class ProductAttributes
{
    /** The relation listings eager load so cards cost no extra query. */
    public const CARD_RELATION = 'cardAttributes';

    /**
     * The attributes a card shows, in the attributes' order.
     *
     * @return list<array{name: string, value: string}>
     */
    public static function card(Product $product): array
    {
        $values = $product->relationLoaded(self::CARD_RELATION)
            ? $product->getRelation(self::CARD_RELATION)
            : $product->cardAttributes()->get();

        return $values
            ->filter(fn ($v) => $v->attribute !== null)
            ->sortBy([fn ($a, $b) => [$a->attribute->sort_order, $a->attribute->name] <=> [$b->attribute->sort_order, $b->attribute->name]])
            ->map(fn ($v) => ['name' => $v->attribute->name, 'value' => $v->value])
            ->values()
            ->all();
    }

    /**
     * Every attribute value of a product, under its group, groups and
     * attributes in their sort order.
     *
     * @return list<array{name: string, attributes: list<array{name: string, value: string}>}>
     */
    public static function groups(Product $product): array
    {
        $values = $product->productAttributes()->with('attribute.group')->get()
            ->filter(fn ($v) => $v->attribute?->group !== null);

        return $values
            ->groupBy(fn ($v) => $v->attribute->group->id)
            ->sortBy(fn ($rows) => [$rows->first()->attribute->group->sort_order, $rows->first()->attribute->group->name])
            ->map(fn ($rows) => [
                'name' => $rows->first()->attribute->group->name,
                'attributes' => $rows
                    ->sortBy([fn ($a, $b) => [$a->attribute->sort_order, $a->attribute->name] <=> [$b->attribute->sort_order, $b->attribute->name]])
                    ->map(fn ($v) => ['name' => $v->attribute->name, 'value' => $v->value])
                    ->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The filterable attributes among a set of products, each with its
     * values and how many of the products carry each one.
     *
     * @param  Builder  $products  a product query, already scoped to what the shopper may see
     * @return list<array{slug: string, name: string, values: list<array{value: string, count: int}>}>
     */
    public static function filters(Builder $products): array
    {
        $ids = (clone $products)->pluck('products.id');
        if ($ids->isEmpty()) {
            return [];
        }

        $rows = \App\Models\ProductAttribute::query()
            ->whereIn('product_id', $ids)
            ->whereHas('attribute', fn ($q) => $q->where('is_filterable', true))
            ->selectRaw('attribute_id, value, COUNT(*) as aggregate')
            ->groupBy('attribute_id', 'value')
            ->get();

        $attributes = Attribute::whereIn('id', $rows->pluck('attribute_id')->unique())->orderBy('sort_order')->orderBy('name')->get();

        return $attributes->map(fn (Attribute $attribute) => [
            'slug' => $attribute->slug,
            'name' => $attribute->name,
            'values' => $rows->where('attribute_id', $attribute->id)
                ->sortBy('value', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn ($r) => ['value' => (string) $r->value, 'count' => (int) $r->aggregate])
                ->values()->all(),
        ])->values()->all();
    }

    /**
     * Narrow a product query by ?attr[slug][]=value. Values of one attribute
     * widen the match; different attributes narrow it. Attributes that are
     * not filterable, and malformed input, are ignored.
     */
    public static function applyFilters(Builder $query, Request $request): void
    {
        $chosen = $request->input('attr');
        if (! is_array($chosen) || $chosen === []) {
            return;
        }

        $attributes = Attribute::where('is_filterable', true)
            ->whereIn('slug', array_map('strval', array_keys($chosen)))
            ->pluck('id', 'slug');

        foreach ($attributes as $slug => $id) {
            $values = array_values(array_filter(array_map(
                fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 255) : '',
                (array) $chosen[$slug]
            ), fn ($v) => $v !== ''));
            if ($values === []) {
                continue;
            }
            $query->whereHas('productAttributes', fn ($q) => $q->where('attribute_id', $id)->whereIn('value', array_slice($values, 0, 50)));
        }
    }
}
