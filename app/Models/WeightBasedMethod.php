<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeightBasedMethod extends Model
{
    protected $fillable = [
        'name', 'admin_name', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function geoZones(): BelongsToMany
    {
        return $this->belongsToMany(GeoZone::class, 'weight_based_method_geo_zone');
    }

    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'weight_based_method_customer_group');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(WeightBasedRate::class)->orderBy('weight_from');
    }
}
