<?php

namespace Extensions\ShippingFlatRate\Models;

use App\Models\CustomerGroup;
use App\Models\GeoZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlatShippingRate extends Model
{
    protected $fillable = ['name', 'geo_zone_id', 'customer_group_id', 'cost', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function geoZone(): BelongsTo
    {
        return $this->belongsTo(GeoZone::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
