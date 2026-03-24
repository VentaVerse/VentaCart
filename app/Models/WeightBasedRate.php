<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightBasedRate extends Model
{
    protected $fillable = [
        'weight_based_method_id', 'weight_from', 'weight_to', 'cost',
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
        return $this->belongsTo(WeightBasedMethod::class, 'weight_based_method_id');
    }
}
