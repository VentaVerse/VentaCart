<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A heading attributes are listed under on the product page (Farm, Pack, Frame). */
class AttributeGroup extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class)->orderBy('sort_order')->orderBy('name');
    }
}
