<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class CustomerGroup extends Model
{
    protected $fillable = ['store_id', 'name', 'description', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * The store's default customer group id (cached). Shares the cache key
     * with CartService::resolvePrice so pricing, shipping and payment rules
     * all resolve "no group" to the same default group.
     */
    public static function defaultId(): ?int
    {
        return Cache::remember(
            'default_customer_group_id',
            3600,
            fn () => static::where('is_default', true)->value('id')
        );
    }

    /**
     * Resolve an effective customer group id. Guests and unregistered
     * customers (admin order entry) have no group; they resolve to the store
     * default group (OpenCart parity) so Default-group pricing, shipping and
     * payment rules apply to them instead of matching nothing.
     */
    public static function effectiveId(?int $groupId): ?int
    {
        return $groupId ?? static::defaultId();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
