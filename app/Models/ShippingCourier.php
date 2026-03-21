<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingCourier extends Model
{
    protected $fillable = ['store_id', 'name', 'tracking_url', 'is_active', 'sort_order'];

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

    public function getTrackingLink(string $trackingNumber): ?string
    {
        if (!$this->tracking_url) {
            return null;
        }

        return str_replace('{tracking_number}', $trackingNumber, $this->tracking_url);
    }
}
