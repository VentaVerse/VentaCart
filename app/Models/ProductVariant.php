<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'price', 'price_modifier', 'cost', 'quantity',
        'weight', 'image', 'is_active', 'legacy_oc_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_modifier' => 'decimal:2',
            'cost' => 'decimal:2',
            'weight' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep `price_modifier` in sync with `price` whenever a variant is saved
        // through the API (or any code path that writes only `price`). The admin
        // form sets both columns explicitly — when both are dirty we trust the
        // caller and skip the recompute.
        static::saving(function (ProductVariant $variant) {
            if ($variant->isDirty('price') && !$variant->isDirty('price_modifier')) {
                $product = $variant->relationLoaded('product')
                    ? $variant->product
                    : Product::find($variant->product_id);
                if ($product) {
                    $variant->price_modifier = round((float) $variant->price - (float) $product->price, 2);
                }
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): BelongsToMany
    {
        return $this->belongsToMany(VariantTypeValue::class, 'product_variant_values');
    }

    // Helper to get a readable name like "Red / Large"
    public function getNameAttribute(): string
    {
        return $this->values->pluck('value')->implode(' / ');
    }
}
