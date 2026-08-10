<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'payment_method', 'payment_method_title', 'payment_reference', 'shipping_method', 'shipping_meta',
        'subtotal', 'shipping_cost', 'total',
        'shipping_courier_id', 'tracking_number', 'tracking_link',
        'shipping_country_name',
        'billing_first_name', 'billing_last_name', 'billing_address_1',
        'billing_city', 'billing_district', 'billing_barangay',
        'billing_state', 'billing_postcode', 'billing_country_id', 'billing_phone', 'billing_country_name',
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
     * Stamp the historical snapshots the order OWNS.
     *
     * Deliberately NOT mass-assignable: this hook is the single writer, so no
     * request payload can ever forge an order's history, and no caller can
     * forget to record it. Every write path (storefront checkout, admin order
     * entry, the ERP API, the HitPay webhook, OrderStatusChanger) gets these
     * columns by construction rather than by remembering to.
     *
     * A snapshot is (re)derived only when its foreign key changes, or when it
     * is still empty (self-healing for rows written before this existed).
     * A key left untouched never re-reads its source, so renaming a status or
     * a courier can never rewrite what an old order says about itself.
     *
     * When the DB nulls a key via ON DELETE SET NULL it fires no model event,
     * so the name survives the delete and later saves leave it alone.
     */
    protected static function booted(): void
    {
        static::saving(function (self $order): void {
            $order->snapshot('order_status_id', 'order_status_name', fn ($id) => OrderStatus::whereKey($id)->value('name'));

            $order->snapshot('created_by', 'created_by_name', fn ($id) => User::whereKey($id)->value('name'));

            $order->snapshot('shipping_courier_id', 'shipping_courier_name', fn ($id) => ShippingCourier::whereKey($id)->value('name'));

            $order->snapshot('customer_id', 'customer_group_name', fn ($id) => Customer::find($id)?->customerGroup?->name);
        });
    }

    /**
     * Resolve one snapshot column from its foreign key.
     *
     * An explicitly assigned snapshot always wins, so a caller that already
     * knows the value (or is restoring history) is never second-guessed.
     */
    protected function snapshot(string $keyColumn, string $snapshotColumn, callable $resolve): void
    {
        if ($this->isDirty($snapshotColumn)) {
            return;
        }

        $keyChanged = $this->isDirty($keyColumn);

        if (! $keyChanged && $this->{$snapshotColumn} !== null) {
            return;
        }

        $key = $this->{$keyColumn};

        if ($key === null) {
            // Only a deliberate key change may clear a name. A key the database
            // nulled behind our back must not erase the history it pointed at.
            if ($keyChanged) {
                $this->{$snapshotColumn} = null;
            }

            return;
        }

        $this->{$snapshotColumn} = $resolve($key);
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

    /**
     * Orders a customer is allowed to see — excludes "missing"/ghost orders.
     *
     * The sentinel for an abandoned checkout (order row written, payment never
     * confirmed) is that the order NEVER REACHED a status, so it never had a
     * status name stamped on it. That is what order_status_name being null
     * means, and it is why this scope keys on the owned column rather than on
     * order_status_id.
     *
     * The foreign key cannot carry this meaning any more: deleting an order
     * status nulls order_status_id on every order that used it, which under
     * the old check made real, paid orders vanish from the customer's history
     * and read as ghosts in admin. The snapshot distinguishes the two cases
     * that the key conflates: never had a status vs. had one whose config row
     * was later deleted.
     */
    public function scopeVisibleToCustomer(Builder $query): Builder
    {
        return $query->whereNotNull('order_status_name');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        // Snapshot first — what was true at order time (see order-snapshots spec).
        $title = $this->attributes['payment_method_title'] ?? null;
        if ($title) {
            return $title;
        }

        $key = $this->payment_method;
        if (!$key) return '';

        if (str_starts_with($key, 'bank_transfer')) {
            $accountId = (int) preg_replace('/^bank_transfer_?/', '', $key);
            if ($accountId) {
                $bankName = BankAccount::where('id', $accountId)->value('bank_name');
                return 'Bank Transfer' . ($bankName ? ': ' . $bankName : '');
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
        // Snapshot first — survives courier deletion/edits.
        $stored = $this->attributes['tracking_link'] ?? null;
        if ($stored) {
            return $stored;
        }

        if (!$this->tracking_number || !$this->shippingCourier) {
            return null;
        }

        return $this->shippingCourier->getTrackingLink($this->tracking_number);
    }
}
