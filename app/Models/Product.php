<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
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
}
