<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MediaFolder extends Model
{
    protected $fillable = ['store_id', 'name', 'slug', 'parent_id', 'is_auto'];

    protected function casts(): array
    {
        return [
            'is_auto' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(StoreMedia::class);
    }

    public function hasProductMedia(): bool
    {
        return $this->media()->whereHas('productImages')->exists();
    }

    public static function getOrCreate(int $storeId, string $name, bool $isAuto = false): self
    {
        $slug = Str::slug($name);

        return static::firstOrCreate(
            ['store_id' => $storeId, 'slug' => $slug, 'parent_id' => null],
            ['name' => $name, 'is_auto' => $isAuto]
        );
    }
}
