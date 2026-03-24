<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethodRate extends Model
{
    protected $fillable = [
        'shipping_method_id', 'weight_from', 'weight_to', 'cost',
    ];

    protected function casts(): array
    {
        return [
            'weight_from' => 'decimal:3',
            'weight_to' => 'decimal:3',
            'cost' => 'decimal:2',
        ];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}
