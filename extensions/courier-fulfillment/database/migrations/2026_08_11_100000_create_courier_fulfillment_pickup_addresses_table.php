<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where parcels leave from. Owned by THIS module, not by core: an install of
 * Venta without courier-fulfillment must carry no trace of it (PRODUCT.md,
 * "an extension owns its own schema and removes it on uninstall"), so the
 * table is created by this extension's install migration and dropped by its
 * uninstall rollback, exactly like the two migrations beside it.
 *
 * It replaces eight flat rows in core `settings` (group=shipping, keys
 * pickup_address / pickup_city / pickup_province / pickup_barangay /
 * pickup_postal_code / pickup_contact_name / pickup_contact_number /
 * pickup_contact_email) which could only ever describe ONE origin. The
 * existing values are carried into the first row by the one-off
 * `fulfillment:import-pickup-address` command, never by a data migration
 * (house rule: a fresh install is clean by default, one-off repair is a
 * command).
 *
 * Shape follows core's `customer_addresses`: only country_id is a foreign
 * key, every finer level (state / city / district / barangay) is stored as
 * the NAME string the cascade produced. That is deliberate and keeps the two
 * sides of a booking speaking the same vocabulary: a courier payload carries
 * names, not PSGC codes, and `cities` in core is empty by design (the deep PH
 * hierarchy is read on demand from database/data/ph/*.json by
 * App\Services\Address\DeepAddressData, never seeded).
 *
 * country_id is a REAL foreign key and stays generic: nothing in the schema
 * says Philippines. The admin form pins the field to the PH row resolved by
 * iso2 at runtime (Extensions\CourierFulfillment\Support\PickupCountry)
 * because every courier this module speaks to today is a PH courier; the day
 * a non-PH courier arrives, only that pin moves.
 *
 * restrictOnDelete, not cascade: deleting a country out from under a live
 * pickup address would silently destroy the origin every booking is measured
 * from. Refusing the country delete is the honest answer.
 *
 * Exactly one row may carry is_default. MySQL has no partial unique index to
 * state that, so it is enforced in the model
 * (Extensions\CourierFulfillment\Models\PickupAddress) on every write, and by
 * the delete rule in PickupAddressController.
 */
return new class extends Migration
{
    private const TABLE = 'courier_fulfillment_pickup_addresses';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();

            // What the operator calls this place, so two warehouses are
            // distinguishable in the booking picker without reading addresses.
            $table->string('label');

            // The two fields the owner asked for by name. Both required.
            //
            // There is deliberately no contact_email. The retired settings
            // block carried one, but NO courier payload has ever contained
            // it: QuadXFulfillment::buildPayload() has no email field at
            // all, and SPX only sends one when creating a shipper account,
            // where it uses config('mail.from.address') on purpose. The
            // courier already holds the account identity behind the API
            // key, so a per-origin email would be a second copy of that
            // identity with nothing reading it and somewhere to drift.
            $table->string('contact_name');
            $table->string('contact_phone', 30);

            $table->text('address_1');
            $table->string('barangay')->nullable();
            $table->string('district')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postcode', 32);
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();

            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
