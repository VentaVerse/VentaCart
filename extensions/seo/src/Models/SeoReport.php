<?php

namespace Extensions\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoReport extends Model
{
    protected $fillable = [
        'store_id',
        'total_products_analyzed',
        'suggestions_generated',
        'auto_accepted',
        'pending_review',
        'health_score',
        'top_opportunities',
        'search_insights',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'top_opportunities' => 'array',
            'search_insights' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
