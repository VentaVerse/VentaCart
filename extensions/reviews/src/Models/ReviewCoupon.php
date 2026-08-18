<?php

namespace Extensions\Reviews\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewCoupon extends Model
{
    protected $fillable = [
        'review_id', 'coupon_code', 'discount_type', 'discount_value', 'expires_at', 'used',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'used' => 'boolean',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
