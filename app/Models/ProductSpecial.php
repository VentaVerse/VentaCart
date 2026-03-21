<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecial extends Model
{
    protected $fillable = [
        'product_id', 'customer_group_id', 'price', 'quantity',
        'display_mode', 'priority', 'date_start', 'date_end',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'date_start' => 'date',
            'date_end' => 'date',
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
