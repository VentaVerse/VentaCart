<?php

namespace Extensions\SmsUnisms;

use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A merchant's named text, sent when an order reaches one of its statuses. */
class SmsTemplate extends Model
{
    protected $table = 'sms_templates';

    protected $fillable = ['title', 'message'];

    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(OrderStatus::class, 'sms_template_statuses', 'sms_template_id', 'order_status_id');
    }
}
