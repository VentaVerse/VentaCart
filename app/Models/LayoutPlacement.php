<?php

namespace App\Models;

use App\Support\StorefrontPageType;
use Illuminate\Database\Eloquent\Model;

class LayoutPlacement extends Model
{
    /** The page_type value meaning "every page". */
    public const EVERYWHERE = '*';

    protected $fillable = [
        'page_type', 'region', 'widget', 'title', 'config', 'sort_order', 'is_enabled',
    ];

    protected $casts = [
        'config' => 'array',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Everything that renders in one region of one page type, in order.
     *
     * Additive, not overriding: a placement on '*' shows on every page and is
     * merged with that page type's own by sort_order. That is what a site-wide
     * promo strip needs. The cost is that a page can carry something the
     * merchant did not put there from this screen, so the editor has to show
     * inherited rows as inherited rather than pretending they are local.
     */
    public static function forRegion(?string $pageType, string $region)
    {
        return static::query()
            ->where('is_enabled', true)
            ->where('region', $region)
            ->where(function ($q) use ($pageType) {
                $q->where('page_type', self::EVERYWHERE);
                if ($pageType !== null) {
                    $q->orWhere('page_type', $pageType);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function inherited(?string $pageType): bool
    {
        return $this->page_type === self::EVERYWHERE && $pageType !== self::EVERYWHERE;
    }

    public function pageLabel(): string
    {
        return $this->page_type === self::EVERYWHERE
            ? 'All pages'
            : (StorefrontPageType::label($this->page_type) ?? $this->page_type);
    }
}
