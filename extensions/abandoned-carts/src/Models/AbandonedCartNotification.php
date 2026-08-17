<?php

namespace Extensions\AbandonedCarts\Models;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCartNotification extends Model
{
    protected $fillable = [
        'cart_id',
        'customer_id',
        'abandoned_cart_rule_id',
        'email_template_id',
        'send_count',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
            'send_count' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AbandonedCartRule::class, 'abandoned_cart_rule_id');
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }
}
