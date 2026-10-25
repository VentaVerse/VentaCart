<?php

namespace Extensions\Banners\Models;

use App\Support\SafeInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Banner extends Model
{
    protected $fillable = [
        'banner_group_id',
        'name', 'bg_image', 'bg_image_mobile', 'bg_color', 'bg_size',
        'link', 'link_new_tab', 'layers',
        'sort_order', 'is_active', 'starts_at', 'ends_at',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(BannerGroup::class, 'banner_group_id');
    }

    protected function casts(): array
    {
        return [
            'layers' => 'array',
            'link_new_tab' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The public address of the background image, encoded, or null. A path
     * that is not a plain storage path gives null rather than a URL a theme
     * would print into a style.
     */
    public function getBgImageUrlAttribute(): ?string
    {
        $path = SafeInput::storagePath($this->bg_image);

        return $path !== '' ? storage_url($path) : null;
    }

    public function getBgImageMobileUrlAttribute(): ?string
    {
        $path = SafeInput::storagePath($this->bg_image_mobile);

        return $path !== '' ? storage_url($path) : null;
    }
}
