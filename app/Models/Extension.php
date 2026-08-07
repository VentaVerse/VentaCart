<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    protected $fillable = [
        'name',
        'version',
        'enabled',
        'installed_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'installed_at' => 'datetime',
    ];
}
