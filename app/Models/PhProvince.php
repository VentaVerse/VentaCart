<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhProvince extends Model
{
    public $timestamps = false;

    protected $fillable = ['ph_region_id', 'code', 'name'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(PhRegion::class, 'ph_region_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(PhCity::class);
    }
}
