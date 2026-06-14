<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingZoneRate extends Model
{
    protected $fillable = [
        'geo_zone_id', 'customer_group_id',
        'weight_from', 'weight_to', 'cost', 'free_shipping_threshold',
    ];

    protected $casts = [
        'weight_from' => 'decimal:3',
        'weight_to' => 'decimal:3',
        'cost' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(GeoZone::class, 'geo_zone_id');
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
