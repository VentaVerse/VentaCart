<?php

namespace Extensions\Seo\Models;

use App\Models\Product;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoSuggestion extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id',
        'product_id',
        'current_meta_title', 'suggested_meta_title',
        'current_meta_description', 'suggested_meta_description',
        'current_meta_keywords', 'suggested_meta_keywords',
        'current_og_title', 'suggested_og_title',
        'current_og_description', 'suggested_og_description',
        'current_slug', 'suggested_slug',
        'status', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function entity(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }
}
