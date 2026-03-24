<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantGroupPrice extends Model
{
    protected $fillable = [
        'product_variant_id', 'customer_group_id', 'price',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
