<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatus extends Model
{
    protected $fillable = ['store_id', 'name', 'slug', 'is_default', 'stock_action', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
