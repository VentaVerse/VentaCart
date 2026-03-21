<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhCity extends Model
{
    public $timestamps = false;

    protected $fillable = ['ph_province_id', 'parent_id', 'code', 'name'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(PhProvince::class, 'ph_province_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhCity::class, 'parent_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(PhCity::class, 'parent_id');
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(PhBarangay::class);
    }
}
