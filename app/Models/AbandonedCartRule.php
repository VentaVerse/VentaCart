<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbandonedCartRule extends Model
{
    protected $fillable = [
        'store_id',
        'email_template_id',
        'customer_group_id',
        'max_sends',
        'min_cart_age_hours',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_sends' => 'integer',
            'min_cart_age_hours' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AbandonedCartNotification::class);
    }
}
