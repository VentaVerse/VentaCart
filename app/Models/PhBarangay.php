<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhBarangay extends Model
{
    public $timestamps = false;

    protected $fillable = ['ph_city_id', 'code', 'name'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(PhCity::class, 'ph_city_id');
    }
}
