<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'store_id', 'customer_id', 'order_status_id', 'created_by',
        'first_name', 'last_name', 'email', 'phone',
        'shipping_first_name', 'shipping_last_name',
        'shipping_address_1', 'shipping_city', 'shipping_district', 'shipping_barangay',
        'shipping_state', 'shipping_postcode', 'shipping_country_id', 'shipping_phone',
        'payment_method', 'payment_reference', 'shipping_method', 'shipping_meta',
        'subtotal', 'shipping_cost', 'total',
        'shipping_courier_id', 'tracking_number',
        'billing_first_name', 'billing_last_name', 'billing_address_1',
        'billing_city', 'billing_district', 'billing_barangay',
        'billing_state', 'billing_postcode', 'billing_country_id', 'billing_phone',
        'comment', 'stock_adjusted',
        'coupon_code', 'coupon_discount',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'stock_adjusted' => 'boolean',
            'coupon_discount' => 'decimal:2',
            'shipping_meta' => 'array',
        ];
    }

    /**
     * Customer-facing shipping label, accessed via $order->courier_label.
     * Falls back through shipping_meta keys before the raw shipping_method
     * column for legacy orders that pre-date the standardized meta shape.
     */
    public function getCourierLabelAttribute(): ?string
    {
        $meta = $this->shipping_meta ?? [];

        if (!empty($meta['courier_label'])) {
            return $meta['courier_label'];
        }

        if (!empty($meta['partner_name'])) {
            $trimmed = preg_replace('/\s*\([^)]*\)\s*$/', '', $meta['partner_name']);
            return $trimmed !== '' ? $trimmed : $meta['partner_name'];
        }

        return $this->shipping_method ?: null;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shippingCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'shipping_country_id');
    }

    public function billingCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'billing_country_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class)->orderBy('created_at', 'desc');
    }

    public function shippingCourier(): BelongsTo
    {
        return $this->belongsTo(ShippingCourier::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        $key = $this->payment_method;
        if (!$key) return '';

        if (str_starts_with($key, 'bank_transfer')) {
            $accountId = (int) preg_replace('/^bank_transfer_?/', '', $key);
            if ($accountId) {
                $bankName = BankAccount::where('id', $accountId)->value('bank_name');
                return 'Bank Transfer' . ($bankName ? ' — ' . $bankName : '');
            }
            return 'Bank Transfer';
        }

        return match ($key) {
            'hitpay' => 'HitPay',
            'cod' => 'Cash on Delivery',
            default => str_replace('_', ' ', ucfirst($key)),
        };
    }

    public function getTrackingLinkAttribute(): ?string
    {
        if (!$this->tracking_number || !$this->shippingCourier) {
            return null;
        }

        return $this->shippingCourier->getTrackingLink($this->tracking_number);
    }
}
