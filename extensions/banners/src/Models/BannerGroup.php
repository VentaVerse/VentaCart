<?php

namespace Extensions\Banners\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BannerGroup extends Model
{
    protected $fillable = [
        'name', 'slug', 'autoplay', 'speed',
        'height', 'height_mobile', 'full_width',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'autoplay' => 'boolean',
            'full_width' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function slides(): HasMany
    {
        return $this->hasMany(Banner::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeSlides(): HasMany
    {
        return $this->slides()->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
