<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id', 'first_name', 'last_name',
        'address_1', 'city', 'district', 'barangay', 'state', 'postcode',
        'country_id', 'phone', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** The number with its country's dialling code, for display. */
    public function getPhoneDisplayAttribute(): ?string
    {
        return \App\Support\Phone::display($this->phone, $this->country?->phone_code);
    }
}
