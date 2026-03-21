<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantTypeValue extends Model
{
    protected $fillable = ['variant_type_id', 'value', 'sort_order'];

    public function variantType(): BelongsTo
    {
        return $this->belongsTo(VariantType::class);
    }
}
