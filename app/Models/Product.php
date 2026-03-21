<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id', 'name', 'slug', 'sku', 'model', 'brand_id', 'description',
        'price', 'cost', 'quantity', 'weight', 'weight_unit',
        'length', 'width', 'height',
        'has_variants', 'image', 'is_active', 'date_available', 'sort_order',
        'minimum_quantity', 'track_stock', 'meta_title',
        'meta_description', 'meta_keywords', 'legacy_oc_id', 'views',
    ];

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
        return $this->hasMany(Review::class);
    }

    public function seoSuggestions(): HasMany
    {
        return $this->hasMany(SeoSuggestion::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('status', 'approved')->latest('published_at');
    }

    public function averageRating(): float
    {
        return (float) $this->approvedReviews()->avg('rating');
    }

    public function reviewCount(): int
    {
        return $this->approvedReviews()->count();
    }
}
