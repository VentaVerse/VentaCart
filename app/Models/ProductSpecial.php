<?php

namespace App\Models;

use App\Models\Concerns\HasDateWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A timed special price for a product, for every customer group, between
 * date_start and date_end (store time, see App\Support\DateWindow). Ask
 * isActiveAt() or the activeAt() scope whether one applies; never compare
 * the dates by hand. price_type says whether value is a fixed price or a
 * percentage off. Prices that are always on are ProductDiscount rows.
 */
class ProductSpecial extends Model
{
    use HasDateWindow;

    protected $fillable = ['product_id', 'price_type', 'value', 'date_start', 'date_end'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'date_start' => 'datetime',
            'date_end' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
