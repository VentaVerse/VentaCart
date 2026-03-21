<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavMenuItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['label', 'type', 'category_id', 'url', 'new_tab', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'new_tab' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getResolvedUrlAttribute(): string
    {
        if ($this->type === 'category' && $this->category) {
            try {
                return route('category.show', $this->category->slug);
            } catch (\Throwable) {
                return '/category/' . $this->category->slug;
            }
        }

        return $this->url ?? '#';
    }
}
