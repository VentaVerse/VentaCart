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
     * v-* token classes (not raw Tailwind) — safe because both consumers
     * (admin/dashboard.blade.php, admin/customers/form.blade.php) render
     * inside the v-admin shell, where the token variables are in scope.
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            'order_placed' => 'text-v-accent bg-v-accent-weak',
            'login', 'registered' => 'text-v-info bg-[color-mix(in_srgb,var(--info)_12%,transparent)]',
            'review_submitted' => 'text-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)]',
            default => 'text-v-secondary bg-v-sunken',
        };
    }
}
