<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotFoundLog extends Model
{
    protected $fillable = [
        'path', 'hits', 'first_hit_at', 'last_hit_at',
        'last_query', 'last_referer', 'last_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'hits' => 'int',
            'first_hit_at' => 'datetime',
            'last_hit_at' => 'datetime',
        ];
    }
}
