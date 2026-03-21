<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'dph_post_id', 'dph_partner_id', 'dph_partner_name',
        'dph_service_type', 'dph_status', 'dph_sub_status',
        'tracking_number', 'tracking_url', 'shareable_link',
        'approximate_fee', 'actual_fee', 'final_fee',
        'booked_at', 'delivered_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'approximate_fee' => 'decimal:2',
            'actual_fee' => 'decimal:2',
            'final_fee' => 'decimal:2',
            'booked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('created_at', 'desc');
    }
}
