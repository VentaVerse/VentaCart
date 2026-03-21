<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingZoneRegion extends Model
{
    protected $fillable = ['shipping_zone_id', 'country_id', 'ph_region_id', 'ph_province_id', 'ph_city_id'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
