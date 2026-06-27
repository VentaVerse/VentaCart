<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethodRestriction extends Model
{
    protected $fillable = [
        'payment_method',
        'geo_zone_ids', 'customer_group_ids', 'category_ids', 'brand_ids',
        'shipping_method_keys', 'currency_codes',
        'min_total', 'max_total', 'min_weight', 'max_weight',
        'min_quantity', 'max_quantity',
        'exclude_geo_zone_ids', 'exclude_customer_group_ids',
        'exclude_category_ids', 'exclude_brand_ids', 'exclude_currency_codes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'geo_zone_ids' => 'array',
            'customer_group_ids' => 'array',
            'category_ids' => 'array',
            'brand_ids' => 'array',
            'shipping_method_keys' => 'array',
            'currency_codes' => 'array',
            'exclude_geo_zone_ids' => 'array',
            'exclude_customer_group_ids' => 'array',
            'exclude_category_ids' => 'array',
            'exclude_brand_ids' => 'array',
            'exclude_currency_codes' => 'array',
            'min_total' => 'decimal:2',
            'max_total' => 'decimal:2',
            'min_weight' => 'decimal:2',
            'max_weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getConditionsSummary(): string
    {
        $parts = [];

        if ($this->min_total) {
            $parts[] = "Min ₱" . number_format($this->min_total, 2);
        }
        if ($this->max_total) {
            $parts[] = "Max ₱" . number_format($this->max_total, 2);
        }
        if ($this->min_weight) {
            $parts[] = "Min {$this->min_weight}kg";
        }
        if ($this->max_weight) {
            $parts[] = "Max {$this->max_weight}kg";
        }
        if ($this->min_quantity) {
            $parts[] = "Min {$this->min_quantity} items";
        }
        if ($this->max_quantity) {
            $parts[] = "Max {$this->max_quantity} items";
        }
        if (!empty($this->geo_zone_ids)) {
            $names = GeoZone::whereIn('id', $this->geo_zone_ids)->pluck('name')->join(', ');
            $parts[] = "Zones: {$names}";
        }
        if (!empty($this->customer_group_ids)) {
            $names = CustomerGroup::whereIn('id', $this->customer_group_ids)->pluck('name')->join(', ');
            $parts[] = "Groups: {$names}";
        }
        if (!empty($this->brand_ids)) {
            $names = Brand::whereIn('id', $this->brand_ids)->pluck('name')->join(', ');
            $parts[] = "Brands: {$names}";
        }
        if (!empty($this->category_ids)) {
            $names = Category::whereIn('id', $this->category_ids)->pluck('name')->join(', ');
            $parts[] = "Categories: {$names}";
        }
        if (!empty($this->shipping_method_keys)) {
            $parts[] = "Shipping: " . implode(', ', $this->shipping_method_keys);
        }
        if (!empty($this->currency_codes)) {
            $parts[] = "Currencies: " . implode(', ', $this->currency_codes);
        }
        if (!empty($this->exclude_customer_group_ids)) {
            $names = CustomerGroup::whereIn('id', $this->exclude_customer_group_ids)->pluck('name')->join(', ');
            $parts[] = "Excl. groups: {$names}";
        }
        if (!empty($this->exclude_geo_zone_ids)) {
            $parts[] = "Excl. " . count($this->exclude_geo_zone_ids) . " zones";
        }
        if (!empty($this->exclude_brand_ids)) {
            $parts[] = "Excl. " . count($this->exclude_brand_ids) . " brands";
        }
        if (!empty($this->exclude_category_ids)) {
            $parts[] = "Excl. " . count($this->exclude_category_ids) . " categories";
        }
        if (!empty($this->exclude_currency_codes)) {
            $parts[] = "Excl. currencies: " . implode(', ', $this->exclude_currency_codes);
        }

        return $parts ? implode(' + ', $parts) : 'No restrictions (always available)';
    }

    public static function getPaymentMethodLabel(string $key): string
    {
        return match ($key) {
            'bank_transfer' => 'Bank Transfer',
            'hitpay' => 'HitPay',
            'cod' => 'Cash on Delivery',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }
}
