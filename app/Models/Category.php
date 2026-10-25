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
     * This category and its active children and grandchildren: what a
     * shopper means by "everything in Phones" is the whole branch.
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        return \Illuminate\Support\Facades\Cache::remember("cat_descendants_{$this->id}", 3600, function () {
            $ids = [$this->id];
            foreach (self::where('parent_id', $this->id)->where('is_active', true)->pluck('id') as $childId) {
                $ids[] = $childId;
                foreach (self::where('parent_id', $childId)->where('is_active', true)->pluck('id') as $grandchildId) {
                    $ids[] = $grandchildId;
                }
            }

            return $ids;
        });
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
