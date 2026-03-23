<?php

namespace Extensions\PhilippineAddress\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhRegion extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name'];

    public function provinces(): HasMany
    {
        return $this->hasMany(PhProvince::class);
    }
}
