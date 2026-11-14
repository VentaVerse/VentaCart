<?php

namespace App\Models;

use App\Models\Concerns\HasRichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Category extends Model
{
    use HasRichText;

    protected $fillable = [
        'store_id', 'parent_id', 'name', 'slug', 'description', 'image',
        'sort_order', 'is_active', 'meta_title', 'meta_description',
        'meta_keywords', 'og_title', 'og_description', 'legacy_oc_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    /**
     * This category and every active category below it, at any depth: what a
     * shopper means by "everything in Guitars" is the whole branch.
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        return \Illuminate\Support\Facades\Cache::remember("cat_descendants_{$this->id}", 3600,
            fn () => \App\Support\CategoryTree::branch((int) $this->id, activeOnly: true));
    }

    protected static function booted(): void
    {
        // A branch, the menu's subcategories and the paths all change with
        // any category, so every cached copy is forgotten together.
        static::saved(fn () => \App\Support\CategoryTree::forget());
        static::deleted(fn () => \App\Support\CategoryTree::forget());
    }

    public function seoSuggestions(): MorphMany
    {
        return $this->morphMany(\Extensions\Seo\Models\SeoSuggestion::class, 'entity', 'entity_type', 'entity_id');
    }

    /** Fields themes print as HTML (see App\Support\RichText). */
    public function richTextFields(): array
    {
        return ['description'];
    }
}
