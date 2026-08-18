<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomerActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'type', 'description',
        'subject_type', 'subject_id', 'properties', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The name of a mark in x-admin.icon, never the mark itself.
     *
     * This used to return raw SVG path data, which put a second copy
     * of the icon set in a model: it drew at 1.5 stroke beside a page
     * of 1.75, carried no aria-hidden, and would have drifted the
     * first time the component's paths were touched.
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'login' => 'user',
            'registered' => 'user-plus',
            'order_placed' => 'orders',
            'cart_add' => 'cart',
            'review_submitted' => 'reviews',
            default => 'info',
        };
    }

    /**
     * A semantic tone key (act, info, warn, quiet) the day sheet's
     * feed maps to its own classes. Utility strings retired with the
     * v2 layer.
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            'order_placed' => 'act',
            'login', 'registered' => 'info',
            'review_submitted' => 'warn',
            default => 'quiet',
        };
    }
}
