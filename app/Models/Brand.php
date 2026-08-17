<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Brand extends Model
{
    protected $fillable = [
        'store_id', 'name', 'slug', 'image', 'description', 'sort_order', 'is_active',
        'meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function seoSuggestions(): MorphMany
    {
        return $this->morphMany(\Extensions\Seo\Models\SeoSuggestion::class, 'entity', 'entity_type', 'entity_id');
    }
}
