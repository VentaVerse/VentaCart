<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoZone extends Model
{
    protected $fillable = ['name', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GeoZoneEntry::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingZoneRate::class, 'geo_zone_id');
    }
}
