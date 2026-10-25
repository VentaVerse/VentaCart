<?php

namespace App\Models;

use App\Models\Concerns\HasRichText;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasRichText;

    protected $fillable = [
        'store_id', 'title', 'slug', 'body',
        'meta_title', 'meta_description', 'og_title', 'og_description',
        'is_active', 'sort_order',
    ];

    public function seoSuggestions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\Extensions\Seo\Models\SeoSuggestion::class, 'entity', 'entity_type', 'entity_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Fields themes print as HTML (see App\Support\RichText). */
    public function richTextFields(): array
    {
        return ['body'];
    }
}
