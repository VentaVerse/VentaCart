<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = [
        'source_path', 'destination_path', 'status_code',
        'preserve_query', 'is_active', 'note',
    ];

    protected function casts(): array
    {
        return [
            'preserve_query' => 'boolean',
            'is_active' => 'boolean',
            'status_code' => 'int',
            'hits' => 'int',
            'last_hit_at' => 'datetime',
        ];
    }
}
