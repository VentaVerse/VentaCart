<?php

namespace App\Models;

use App\Models\Concerns\HasDateWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A special price for a product, for one customer group or all of them,
 * from a minimum quantity, between date_start and date_end (store time,
 * see App\Support\DateWindow). Ask isActiveAt() or the activeAt() scope
 * whether one applies; never compare the dates by hand.
 */
class ProductSpecial extends Model
{
    use HasDateWindow;

    protected $fillable = [
        'product_id', 'customer_group_id', 'price', 'quantity',
        'display_mode', 'priority', 'date_start', 'date_end',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'date_start' => 'datetime',
            'date_end' => 'datetime',
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
