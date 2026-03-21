<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoZoneEntry extends Model
{
    protected $fillable = ['geo_zone_id', 'country_id', 'zone_id', 'ph_region_id', 'ph_province_id', 'ph_city_id'];

    public function geoZone(): BelongsTo
    {
        return $this->belongsTo(GeoZone::class);
    }
}
