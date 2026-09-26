<?php

namespace App\Models;

use App\Support\Revenue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatus extends Model
{
    protected $fillable = ['store_id', 'name', 'is_default', 'deducts_stock', 'counts_as_revenue', 'customer_can_cancel', 'tone', 'sort_order'];

    protected $attributes = ['tone' => \App\Support\StatusTone::NEUTRAL];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            // A status either takes goods OUT of inventory or it does not
            // (owner ruling, 2026-08-08). There is no "increment" counterpart:
            // returning stock is the automatic reversal StockService performs
            // when an order whose stock is out reaches a non-deducting status.
            'deducts_stock' => 'boolean',
            // Whether orders sitting at this status are money the merchant
            // keeps (owner ruling, 2026-08-09). Seeded from deducts_stock and
            // independent of it thereafter: a lost or damaged parcel deducted
            // stock and earned nothing. Read ONLY through App\Support\Revenue.
            'counts_as_revenue' => 'boolean',
            'customer_can_cancel' => 'boolean',
            // Whether reaching this status sends the customer an email. Off
            // by default: a fresh status is silent until the store says
            // otherwise. SMS is a separate list kept by the UniSMS extension.
        ];
    }

    /**
     * The revenue resolver memoises its id set for the life of a request, so
     * any write to a status has to drop that memo or an admin who saves the
     * page and is redirected back could be shown the figure they just changed
     * away from. Mass updates through the query builder bypass these events
     * by design; the only one core performs (clearing is_default) cannot
     * change which statuses are revenue.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Revenue::flush());
        static::deleted(fn () => Revenue::flush());
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
