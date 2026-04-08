<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'discount_value', 'min_order_total', 'max_discount',
        'free_shipping', 'customer_eligibility', 'customer_group_id',
        'usage_limit', 'usage_per_customer', 'times_used',
        'date_start', 'date_end', 'is_active', 'source',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_total' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'free_shipping' => 'boolean',
            'is_active' => 'boolean',
            'times_used' => 'integer',
            'usage_limit' => 'integer',
            'usage_per_customer' => 'integer',
            'date_start' => 'date',
            'date_end' => 'date',
        ];
    }

    public function restrictions(): HasMany
    {
        return $this->hasMany(CouponRestriction::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->date_end !== null && $this->date_end->isPast();
    }
}
