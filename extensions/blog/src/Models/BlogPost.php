<?php

namespace Extensions\Blog\Models;

use App\Models\Tag;
use App\Models\Concerns\HasRichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BlogPost extends Model
{
    use HasRichText;

    /** The longest search the blog accepts, in characters. */
    public const SEARCH_MAX = 100;

    /** The shortest search the blog accepts, in characters. */
    public const SEARCH_MIN = 2;

    /** Words beyond this many are ignored, so one search cannot grow the query without end. */
    public const SEARCH_WORDS = 6;

    /** The columns a search reads. */
    private const SEARCH_COLUMNS = ['title', 'excerpt', 'content'];

    protected $fillable = [
        'title', 'slug', 'content', 'excerpt', 'featured_image',
        'blog_category_id', 'view_count', 'is_published', 'published_at',
        'meta_title', 'meta_description', 'og_title', 'og_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** Posts a visitor may read: published, with a publishing date that has come. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_published'), true)
            ->whereNotNull($this->qualifyColumn('published_at'))
            ->where($this->qualifyColumn('published_at'), '<=', now());
    }

    /**
     * Posts whose title, excerpt or body contain every word of the search.
     * Each word is bound as a parameter and its LIKE wildcards are escaped,
     * so % and _ in a search match themselves. The escape character is
     * named in the query because MySQL and SQLite have different defaults.
     */
    public function scopeMatching(Builder $query, string $search): Builder
    {
        $words = array_slice(preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::SEARCH_WORDS);
        $grammar = $query->getQuery()->getGrammar();

        foreach ($words as $word) {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word) . '%';
            $query->where(function (Builder $q) use ($like, $grammar) {
                foreach (self::SEARCH_COLUMNS as $column) {
                    $q->orWhereRaw($grammar->wrap($this->qualifyColumn($column)) . " LIKE ? ESCAPE '!'", [$like]);
                }
            });
        }

        return $query;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'blog_post_tag');
    }

    public function seoSuggestions(): MorphMany
    {
        return $this->morphMany(\Extensions\Seo\Models\SeoSuggestion::class, 'entity', 'entity_type', 'entity_id');
    }

    /** Fields themes print as HTML (see App\Support\RichText). */
    public function richTextFields(): array
    {
        return ['content'];
    }
}
