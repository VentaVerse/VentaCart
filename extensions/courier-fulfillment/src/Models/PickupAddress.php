<?php

namespace Extensions\CourierFulfillment\Models;

use App\Models\Country;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One place parcels leave from. The module's single source of truth for a
 * courier payload's sender/pickup block, replacing the retired
 * settings(group=shipping, key=pickup_*) rows.
 *
 * Exactly one row carries is_default, enforced HERE on every write rather
 * than hoped for: the first row ever created becomes the default (a store
 * with addresses but no default could not book at all), and any row saved
 * with is_default true demotes every other row in the same transaction. The
 * demotion runs as a mass update, which fires no model events, so it cannot
 * recurse.
 *
 * The finer address levels are NAME strings, matching core's
 * customer_addresses and the vocabulary the deep-address cascade produces;
 * only country_id is a foreign key. See the create migration for why.
 */
class PickupAddress extends Model
{
    protected $table = 'courier_fulfillment_pickup_addresses';

    protected $fillable = [
        'label',
        'contact_name',
        'contact_phone',
        'address_1',
        'barangay',
        'district',
        'city',
        'state',
        'postcode',
        'country_id',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'country_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $address) {
            // A store's first pickup address is its default whether or not
            // the form said so. Without this, an operator who never ticks
            // the box ends up with addresses and no origin, and every
            // booking refuses.
            if (self::query()->count() === 0) {
                $address->is_default = true;
            }
        });

        static::saved(function (self $address) {
            if (!$address->is_default) {
                return;
            }

            // Mass update: fires no model events, so this cannot re-enter
            // saved() and recurse.
            self::query()
                ->whereKeyNot($address->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** The store's origin, or null when none has been added yet. */
    public static function defaultAddress(): ?self
    {
        return self::query()->where('is_default', true)->orderBy('id')->first();
    }

    /**
     * The address a booking should leave from: the one explicitly chosen at
     * booking time when it exists, the default otherwise. Both couriers and
     * FulfillmentService go through this, so "which address did this parcel
     * ship from" has one answer and one implementation.
     */
    public static function resolveFor(array $opts): ?self
    {
        $chosen = $opts['pickup_address_id'] ?? null;

        if ($chosen !== null && $chosen !== '') {
            $address = self::query()->find((int) $chosen);
            if ($address) {
                return $address;
            }
        }

        return self::defaultAddress();
    }

    /** Promote this row, demoting whatever held the flag, as one write. */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            self::query()->where('is_default', true)->update(['is_default' => false]);
            $this->forceFill(['is_default' => true])->save();
        });
    }

    /** One readable line for a picker row, a table cell, or a shipment's meta. */
    public function summary(): string
    {
        $parts = array_filter([
            $this->address_1,
            $this->barangay,
            $this->district,
            $this->city,
            trim($this->state . ' ' . $this->postcode),
        ], fn ($p) => trim((string) $p) !== '');

        return implode(', ', array_map(fn ($p) => trim((string) $p), $parts));
    }
}
