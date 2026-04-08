<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRestriction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'coupon_id', 'restriction_type', 'restriction_id',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
