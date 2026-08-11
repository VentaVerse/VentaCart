<?php

namespace Extensions\CourierFulfillment\Console\Commands;

use App\Models\Setting;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\Support\PickupCountry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off carry-over of the retired flat pickup origin
 * (settings group=shipping, keys pickup_*) into the module's own
 * courier_fulfillment_pickup_addresses table, as the store's default.
 *
 * A COMMAND, not a data migration, on purpose: a fresh install has no legacy
 * settings to move and must stay clean, so repairing an EXISTING store is an
 * explicit thing an operator runs once (house rule, see
 * feedback_no_install_specific_migrations).
 *
 * Idempotent in both directions. It refuses to run once any pickup address
 * exists (that is the "already imported" signal, and re-importing would
 * duplicate the origin or fight the operator's own edits), and it is a no-op
 * when there are no pickup_* settings left to read. On success it deletes the
 * settings rows it consumed, because the whole point is that exactly one
 * source of truth survives.
 */
class ImportPickupAddressFromSettings extends Command
{
    protected $signature = 'fulfillment:import-pickup-address {--dry-run : Report what would be imported without writing anything}';

    protected $description = 'Move the retired pickup_* shipping settings into the fulfillment module pickup address table, once.';

    /**
     * The retired keys, in settings group "shipping". All eight are read and
     * then deleted, so no pickup_* row survives the import; only seven of
     * them map onto a column. pickup_contact_email has no destination on
     * purpose (owner ruling): no courier payload has ever carried a pickup
     * contact email, the courier account identity is the API key, and a
     * per-origin copy of it would only have somewhere to drift.
     */
    private const KEYS = [
        'pickup_address',
        'pickup_barangay',
        'pickup_city',
        'pickup_province',
        'pickup_postal_code',
        'pickup_contact_name',
        'pickup_contact_number',
        'pickup_contact_email',
    ];

    public function handle(): int
    {
        if (PickupAddress::query()->exists()) {
            $this->line('Pickup addresses already exist. Nothing imported.');

            return self::SUCCESS;
        }

        $values = Setting::query()
            ->where('group', 'shipping')
            ->whereIn('key', self::KEYS)
            ->pluck('value', 'key')
            ->map(fn ($v) => trim((string) $v))
            ->all();

        $values = array_filter($values, fn ($v) => $v !== '');

        if ($values === []) {
            $this->line('No pickup_* settings found. Nothing imported.');

            return self::SUCCESS;
        }

        $country = PickupCountry::tryResolve();
        if (!$country) {
            $this->error(PickupCountry::missingMessage());

            return self::FAILURE;
        }

        $attributes = [
            'label'         => 'Store pickup',
            'contact_name'  => $values['pickup_contact_name'] ?? 'Sender',
            'contact_phone' => $values['pickup_contact_number'] ?? '',
            'address_1'     => $values['pickup_address'] ?? '',
            'barangay'      => $values['pickup_barangay'] ?? null,
            // The retired block had no district field at all. Left null, so
            // both couriers fall back exactly as they did before (SPX's
            // sender_district to the city, QuadX's district to the barangay)
            // and the payload they build is unchanged.
            'district'      => null,
            'city'          => $values['pickup_city'] ?? '',
            'state'         => $values['pickup_province'] ?? '',
            'postcode'      => $values['pickup_postal_code'] ?? '',
            'country_id'    => $country->id,
            'is_default'    => true,
        ];

        if ($this->option('dry-run')) {
            $this->line('Would import: ' . $attributes['address_1'] . ', ' . $attributes['barangay'] . ', ' . $attributes['city'] . ', ' . $attributes['state'] . ' ' . $attributes['postcode']);
            $this->line('Contact: ' . $attributes['contact_name'] . ' ' . $attributes['contact_phone']);
            $this->line('Would then delete ' . count($values) . ' pickup_* settings rows.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($attributes, $values) {
            PickupAddress::create($attributes);

            Setting::query()
                ->where('group', 'shipping')
                ->whereIn('key', array_keys($values))
                ->delete();
        });

        $this->info('Imported "' . $attributes['label'] . '" as the default pickup address, and removed ' . count($values) . ' retired pickup_* settings.');

        return self::SUCCESS;
    }
}
