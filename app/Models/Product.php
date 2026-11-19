<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Concerns\HasRichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasRichText;

    protected $fillable = [
        'store_id', 'name', 'slug', 'sku', 'brand_id', 'description',
        'price', 'cost', 'quantity', 'weight', 'weight_unit',
        'length', 'width', 'height',
        'has_variants', 'image', 'is_active', 'date_available', 'sort_order',
        'minimum_quantity', 'track_stock', 'meta_title',
        'meta_description', 'meta_keywords', 'og_title', 'og_description',
        'legacy_oc_id', 'views',
    ];

    protected static function booted(): void
    {
        // Storefront customer-group visibility (admin + CLI bypass it).
        static::addGlobalScope(new \App\Models\Scopes\CustomerGroupVisibilityScope());

        static::creating(function (Product $product) {
            if (is_null($product->date_available)) {
                $product->date_available = now();
            }
        });

        static::created(fn () => \App\Services\BadgeService::clearCache());
        static::updated(fn () => \App\Services\BadgeService::clearCache());
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'weight' => 'decimal:3',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'has_variants' => 'boolean',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'date_available' => 'date',
        ];
    }

    /**
     * Fix legacy YouTube iframes missing required attributes for modern browsers.
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (!$value || !str_contains($value, 'youtube.com/embed')) {
                    return $value;
                }

                return preg_replace_callback(
                    '/<iframe([^>]*youtube\.com\/embed[^>]*)>/i',
                    function ($match) {
                        $attrs = $match[1];

                        // Remove empty sandbox attribute — it blocks YouTube's JS player entirely
                        $attrs = preg_replace('/\s*sandbox=["\']["\']/', '', $attrs);

                        // Add allow attribute if missing
                        if (!str_contains($attrs, 'allow=')) {
                            $attrs .= ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"';
                        }

                        // Add referrerpolicy if missing
                        if (!str_contains($attrs, 'referrerpolicy=')) {
                            $attrs .= ' referrerpolicy="strict-origin-when-cross-origin"';
                        }

                        // Ensure https protocol (replace //www.youtube with https://www.youtube)
                        $attrs = preg_replace('/src=["\']\/\/www\.youtube/', 'src="https://www.youtube', $attrs);

                        return '<iframe' . $attrs . '>';
                    },
                    $value
                );
            },
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'customer_group_product');
    }

    /** This product's attribute values (origin, weight ...), one per attribute. */
    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /** The values of attributes marked to show on product cards, with their attribute. */
    public function cardAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)
            ->whereHas('attribute', fn ($q) => $q->where('show_on_card', true))
            ->with('attribute');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variantTypes(): HasMany
    {
        return $this->hasMany(VariantType::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * A product with variants carries the stock of the ones that are for sale.
     *
     * Owner ruling 2026-10-16: a switched-off variant's units are not stock
     * the shop can sell, so they are left out of the parent's total. The one
     * place that total is computed; the admin save, the ERP and every stock
     * move after an order all come through here.
     */
    public function syncStockFromVariants(): void
    {
        if (! $this->has_variants) {
            return;
        }

        $this->update(['quantity' => (int) $this->variants()->where('is_active', true)->sum('quantity')]);
    }

    public function specials(): HasMany
    {
        return $this->hasMany(ProductSpecial::class)->orderBy('priority');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(\Extensions\Reviews\Models\Review::class);
    }

    public function seoSuggestions(): HasMany
    {
        return $this->hasMany(\Extensions\Seo\Models\SeoSuggestion::class);
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(\Extensions\Reviews\Models\Review::class)->where('status', 'approved')->latest('published_at');
    }

    public function averageRating(): float
    {
        return \App\Support\Reviews::enabled() ? (float) $this->approvedReviews()->avg('rating') : 0.0;
    }

    public function reviewCount(): int
    {
        return \App\Support\Reviews::enabled() ? $this->approvedReviews()->count() : 0;
    }

    /**
     * Products on sale for one customer group: the special the cart charges
     * for one unit (see CartService::winningSpecial) is shown as a sale.
     *
     * A special belongs to one group, or to every group when it names none,
     * so a sale kept for another group never lists a product at this
     * shopper's regular price. Only specials for one unit count: a volume
     * tier does not change the price on the card. And a product is on sale
     * only when its sale is the special that wins: a quiet price that
     * outranks it is what the card shows, with no sale on it.
     *
     * The rule is written in SQL so a paginated listing can filter by it.
     * It mirrors winningSpecial(): the deepest tier at or below one unit,
     * then the lower priority, then the lower price, then the earlier row.
     */
    public function scopeOnSaleFor(Builder $query, ?int $customerGroupId): Builder
    {
        $applies = fn (Builder $special) => $special
            ->where($special->qualifyColumn('quantity'), '<=', 1)
            ->where(fn (Builder $g) => $g->whereNull($g->qualifyColumn('customer_group_id'))
                ->orWhere($g->qualifyColumn('customer_group_id'), $customerGroupId))
            ->activeAt();

        return $query->whereHas('specials', function (Builder $sale) use ($applies) {
            $applies($sale)->where($sale->qualifyColumn('display_mode'), 'sale');

            // No other special that applies outranks this sale.
            $rivalModel = (new ProductSpecial())->setTable('rival');
            $rival = $rivalModel->newQuery()->from($sale->getModel()->getTable(), 'rival');
            $applies($rival)
                ->whereColumn('rival.product_id', $sale->qualifyColumn('product_id'))
                ->where(fn (Builder $better) => $better
                    ->whereColumn('rival.quantity', '>', $sale->qualifyColumn('quantity'))
                    ->orWhere(fn (Builder $tie) => $tie
                        ->whereColumn('rival.quantity', $sale->qualifyColumn('quantity'))
                        ->where(fn (Builder $rank) => $rank
                            ->whereColumn('rival.priority', '<', $sale->qualifyColumn('priority'))
                            ->orWhere(fn (Builder $samePriority) => $samePriority
                                ->whereColumn('rival.priority', $sale->qualifyColumn('priority'))
                                ->where(fn (Builder $price) => $price
                                    ->whereColumn('rival.price', '<', $sale->qualifyColumn('price'))
                                    ->orWhere(fn (Builder $samePrice) => $samePrice
                                        ->whereColumn('rival.price', $sale->qualifyColumn('price'))
                                        ->whereColumn('rival.id', '<', $sale->qualifyColumn('id'))))))));

            $sale->whereNotExists($rival->toBase());
        });
    }

    /** Fields themes print as HTML (see App\Support\RichText). */
    public function richTextFields(): array
    {
        return ['description'];
    }
}
