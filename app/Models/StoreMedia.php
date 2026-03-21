<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class StoreMedia extends Model
{
    protected $table = 'store_media';

    protected $fillable = [
        'store_id', 'media_folder_id', 'path', 'disk', 'original_filename',
        'mime_type', 'file_size', 'width', 'height', 'is_temp',
    ];

    protected function casts(): array
    {
        return [
            'is_temp' => 'boolean',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'media_folder_id');
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        $info = pathinfo($this->path);
        $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');

        if (Storage::disk($this->disk)->exists($thumbPath)) {
            return Storage::disk($this->disk)->url($thumbPath);
        }

        return $this->url;
    }

    public function getIsUsedAttribute(): bool
    {
        return $this->productImages()->exists();
    }

    public function getHumanFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }

    public function getDimensionsAttribute(): ?string
    {
        if ($this->width && $this->height) {
            return $this->width . 'x' . $this->height;
        }
        return null;
    }
}
