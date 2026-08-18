<?php

namespace Extensions\ShippingFree\Models;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\GeoZone;
use Illuminate\Database\Eloquent\Model;

class FreeShippingRule extends Model
{
    protected $fillable = [
        'name', 'checkout_name', 'min_order_total', 'min_quantity',
        'brand_ids', 'category_ids', 'customer_group_ids', 'shipping_zone_ids',
        'exclude_brand_ids', 'exclude_category_ids', 'exclude_customer_group_ids',
        'apply_whole_cart', 'show_badge', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_order_total' => 'decimal:2',
            'brand_ids' => 'array',
            'category_ids' => 'array',
            'customer_group_ids' => 'array',
            'shipping_zone_ids' => 'array',
            'exclude_brand_ids' => 'array',
            'exclude_category_ids' => 'array',
            'exclude_customer_group_ids' => 'array',
            'apply_whole_cart' => 'boolean',
            'show_badge' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getConditionsSummary(): string
    {
        $parts = [];
        if ($this->min_order_total) {
            $parts[] = "Min ₱" . number_format($this->min_order_total, 2);
        }
        if ($this->min_quantity) {
            $parts[] = "Min {$this->min_quantity} items";
        }
        if (!empty($this->brand_ids)) {
            $names = Brand::whereIn('id', $this->brand_ids)->pluck('name')->join(', ');
            $parts[] = "Brands: {$names}";
        }
        if (!empty($this->category_ids)) {
            $names = Category::whereIn('id', $this->category_ids)->pluck('name')->join(', ');
            $parts[] = "Categories: {$names}";
        }
        if (!empty($this->customer_group_ids)) {
            $names = CustomerGroup::whereIn('id', $this->customer_group_ids)->pluck('name')->join(', ');
            $parts[] = "Groups: {$names}";
        }
        if (!empty($this->shipping_zone_ids)) {
            $names = GeoZone::whereIn('id', $this->shipping_zone_ids)->pluck('name')->join(', ');
            $parts[] = "Zones: {$names}";
        }
        if (!empty($this->exclude_customer_group_ids)) {
            $names = CustomerGroup::whereIn('id', $this->exclude_customer_group_ids)->pluck('name')->join(', ');
            $parts[] = "Excl. groups: {$names}";
        }
        if (!empty($this->exclude_brand_ids)) {
            $parts[] = "Excl. " . count($this->exclude_brand_ids) . " brands";
        }
        if (!empty($this->exclude_category_ids)) {
            $parts[] = "Excl. " . count($this->exclude_category_ids) . " categories";
        }
        return $parts ? implode(' + ', $parts) : 'No conditions (always free)';
    }
}
