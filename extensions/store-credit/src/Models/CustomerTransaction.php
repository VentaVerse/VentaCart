<?php

namespace Extensions\StoreCredit\Models;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One signed ledger entry (OpenCart's customer_transaction shape):
 * positive = credit granted, negative = credit spent. A customer's
 * balance is the SUM, never a stored column, so it cannot drift.
 */
class CustomerTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'order_id', 'created_by', 'description', 'amount', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
