<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FooterColumn extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'type', 'config', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_enabled' => 'boolean',
        ];
    }
}
