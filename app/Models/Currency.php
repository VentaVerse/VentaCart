<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'symbol', 'rate', 'is_default', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Currency $currency) {
            $currency->updated_at = now();
        });
    }
}
