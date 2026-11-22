<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A price that is always on for a product: for one customer group, or for
 * every group when customer_group_id is null, from a minimum quantity.
 * price_type says whether value is a fixed price or a percentage off.
 */
class ProductDiscount extends Model
{
    protected $fillable = ['product_id', 'customer_group_id', 'quantity', 'price_type', 'value'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'value' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
