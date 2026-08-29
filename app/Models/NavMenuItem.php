<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * One entry in a composed menu. Its type says what it points at and how it
 * opens; its config carries the richer choices (a category's mega menu,
 * an HTML dropdown, an accent colour). Items nest through parent_id.
 */
class NavMenuItem extends Model
{
    public $timestamps = false;

    /** Every kind of item, with the word the composer shows for it. */
    public const TYPES = [
        'custom' => 'Link',
        'category' => 'Category',
        'page' => 'Page',
        'categories' => 'All categories',
        'brands' => 'Brands',
        'html' => 'Custom HTML dropdown',
        'system' => 'Store page',
        'home' => 'Home',
        'title' => 'Title',
        'separator' => 'Separator',
    ];

    /** The store's own pages a system item may point at. */
    public const SYSTEM = [
        'home' => ['label' => 'Home', 'route' => 'home'],
        'blog' => ['label' => 'Blog', 'route' => 'blog.index'],
        'brands' => ['label' => 'Brands', 'route' => 'brand.index'],
        'search' => ['label' => 'Search', 'route' => 'search.results'],
        'contact' => ['label' => 'Contact', 'route' => 'contact.index'],
        'account' => ['label' => 'My account', 'route' => 'account.dashboard'],
        'cart' => ['label' => 'Cart', 'route' => 'cart.index'],
        'checkout' => ['label' => 'Checkout', 'route' => 'checkout.index'],
        'login' => ['label' => 'Sign in', 'route' => 'customer.login'],
    ];

    protected $fillable = ['menu_id', 'parent_id', 'label', 'type', 'category_id', 'page_id', 'url', 'new_tab', 'config', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'new_tab' => 'boolean',
            'is_enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(NavMenu::class, 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** Where the item leads, or null for the kinds that lead nowhere. */
    public function getResolvedUrlAttribute(): ?string
    {
        $route = fn (string $name, array $params = [], string $fallback = '#') => Route::has($name) ? route($name, $params) : $fallback;

        return match ($this->type) {
            'category' => $this->category ? $route('category.show', [$this->category->slug], '/category/' . $this->category->slug) : '#',
            'page' => $this->page ? $route('page.show', [$this->page->slug], '/' . $this->page->slug) : '#',
            'home' => url('/'),
            'categories', 'brands' => $this->type === 'brands' ? $route('brand.index') : '#',
            'system' => ($sys = self::SYSTEM[$this->config['page'] ?? ''] ?? null) ? $route($sys['route']) : '#',
            'title', 'separator' => null,
            default => $this->url ?: '#',
        };
    }
}
