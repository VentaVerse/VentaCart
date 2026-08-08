<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatus extends Model
{
    protected $fillable = ['store_id', 'name', 'is_default', 'deducts_stock', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            // A status either takes goods OUT of inventory or it does not
            // (owner ruling, 2026-08-08). There is no "increment" counterpart:
            // returning stock is the automatic reversal StockService performs
            // when an order whose stock is out reaches a non-deducting status.
            'deducts_stock' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
