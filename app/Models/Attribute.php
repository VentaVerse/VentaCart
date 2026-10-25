<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A named product fact (origin, weight, lens width). Each product holds at
 * most one value for it. The slug names it in filter URLs: ?attr[origin][]=…
 */
class Attribute extends Model
{
    protected $fillable = ['attribute_group_id', 'name', 'slug', 'sort_order', 'is_filterable', 'show_on_card'];

    protected $casts = [
        'is_filterable' => 'boolean',
        'show_on_card' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Attribute $attribute) {
            if ($attribute->slug === null || $attribute->slug === '' || $attribute->isDirty('name')) {
                $attribute->slug = static::uniqueSlug($attribute->name, $attribute->id);
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class, 'attribute_group_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    private static function uniqueSlug(string $name, ?int $ignore): string
    {
        $base = Str::slug($name) ?: 'attribute';
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }
}
