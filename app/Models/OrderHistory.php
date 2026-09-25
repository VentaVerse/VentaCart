<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderHistory extends Model
{
    protected $fillable = [
        'order_id', 'order_status_id', 'created_by',
        'comment', 'notify_customer', 'notifications',
    ];

    protected function casts(): array
    {
        return [
            'notify_customer' => 'boolean',
            'notifications' => 'array',
        ];
    }

    /**
     * Stamp the status name and the actor's name onto the history row itself.
     *
     * A history row is the audit trail: what it says happened, and who did it,
     * must outlive the status row and the staff account it referenced. Not
     * mass-assignable for the same reason as on Order: this hook is the only
     * writer, so all seven history-writing call sites (admin, checkout,
     * OrderStatusChanger, the ERP API, both HitPay paths, and the courier
     * fulfillment extension) are covered without touching any of them.
     *
     * created_by is legitimately null for system-initiated changes (webhooks,
     * polls, checkout confirmation); those rows keep a null actor name.
     */
    protected static function booted(): void
    {
        static::saving(function (self $history): void {
            if (! $history->isDirty('order_status_name') && $history->order_status_name === null && $history->order_status_id !== null) {
                $history->order_status_name = OrderStatus::whereKey($history->order_status_id)->value('name');
            }

            if (! $history->isDirty('created_by_name') && $history->created_by_name === null && $history->created_by !== null) {
                $history->created_by_name = User::whereKey($history->created_by)->value('name');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
