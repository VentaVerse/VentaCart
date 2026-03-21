<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['store_id', 'name', 'is_default', 'sort_order', 'status'];

    protected $casts = [
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function regions(): HasMany
    {
        return $this->hasMany(ShippingZoneRegion::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingZoneRate::class);
    }
}
