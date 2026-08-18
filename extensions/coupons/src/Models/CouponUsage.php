<?php

namespace Extensions\Coupons\Models;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'coupon_id', 'customer_id', 'order_id', 'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
