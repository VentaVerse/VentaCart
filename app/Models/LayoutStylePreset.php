<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A named row style a merchant saved to reuse. Belongs to a theme. */
class LayoutStylePreset extends Model
{
    protected $fillable = ['theme', 'name', 'style'];

    protected $casts = ['style' => 'array'];
}
