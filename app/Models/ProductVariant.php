<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'price', 'cost', 'quantity',
        'weight', 'image', 'is_active', 'legacy_oc_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'weight' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): BelongsToMany
    {
        return $this->belongsToMany(VariantTypeValue::class, 'product_variant_values');
    }

    public function groupPrices(): HasMany
    {
        return $this->hasMany(ProductVariantGroupPrice::class);
    }

    // Helper to get a readable name like "Red / Large"
    public function getNameAttribute(): string
    {
        return $this->values->pluck('value')->implode(' / ');
    }
}
