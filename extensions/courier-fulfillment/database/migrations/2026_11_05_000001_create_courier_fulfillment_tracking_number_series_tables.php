<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking number series: blocks of tracking numbers a courier assigns to the
 * store, for couriers that do not issue a number per booking (LBC). The store
 * hands out the numbers itself, so it must never issue one twice and never
 * step outside the block.
 *
 * Two tables, both owned by this extension and dropped on uninstall:
 *
 * - `courier_fulfillment_tracking_number_series`: one row per assigned block,
 *   holding its first and last number and the highest number issued so far.
 *   Claims lock this row, so two bookings can never take the same number.
 * - `courier_fulfillment_tracking_numbers`: one row per number issued, with
 *   its state. `reserved` while a request is in flight, `spent` once the
 *   courier saved it (or may have), `released` when the courier definitely
 *   rejected the request, so the number goes back to be issued again.
 */
return new class extends Migration
{
    private const SERIES = 'courier_fulfillment_tracking_number_series';

    private const NUMBERS = 'courier_fulfillment_tracking_numbers';

    public function up(): void
    {
        if (!Schema::hasTable(self::SERIES)) {
            Schema::create(self::SERIES, function (Blueprint $table) {
                $table->id();
                $table->string('courier', 32);
                $table->unsignedBigInteger('first_number');
                $table->unsignedBigInteger('last_number');
                // Null until the first number of the block is issued.
                $table->unsignedBigInteger('last_issued_number')->nullable();
                $table->timestamps();

                // Named explicitly: the generated names pass MySQL's 64-character limit.
                $table->unique(['courier', 'first_number'], 'cf_tn_series_courier_first_unique');
            });
        }

        if (!Schema::hasTable(self::NUMBERS)) {
            Schema::create(self::NUMBERS, function (Blueprint $table) {
                $table->id();
                $table->foreignId('series_id');
                $table->foreign('series_id', 'cf_tn_numbers_series_foreign')
                    ->references('id')->on(self::SERIES)
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('number');
                $table->string('status', 16);
                // Plain column, no foreign key onto core's orders table: an
                // extension adds no constraints to tables it does not own.
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('outcome', 255)->nullable();
                $table->timestamps();

                $table->unique(['series_id', 'number'], 'cf_tn_numbers_series_number_unique');
                $table->index(['series_id', 'status'], 'cf_tn_numbers_series_status_index');
                $table->index('order_id', 'cf_tn_numbers_order_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::NUMBERS);
        Schema::dropIfExists(self::SERIES);
    }
};
