<?php

namespace Extensions\SmsUnisms;

use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A merchant's named text, sent when an order reaches its one status. */
class SmsTemplate extends Model
{
    protected $table = 'sms_templates';

    protected $fillable = ['order_status_id', 'title', 'message'];

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }
}
