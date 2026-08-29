<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fulfillment module stops guessing its three target statuses (booked,
 * unbooked, cancel) when the merchant has not chosen them (owner ruling
 * 2026-08-30). Until now an unset setting resolved through a status NAME
 * and then the first placed status by sort order, and the settings hub
 * showed that guess as "Use default (...)".
 *
 * Continuity: an EXISTING store that was living on those guesses gets them
 * written into the settings once, here, so nothing about its booking,
 * unbooking or cancelling changes at upgrade. A fresh install has nothing
 * placed and nothing to pin, and starts with every select on Not selected.
 * Settings already chosen are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (\App\Support\InstallState::isFreshInstall()) {
            return;
        }
        if (! Schema::hasTable('settings') || ! Schema::hasTable('order_statuses') || ! Schema::hasTable('courier_fulfillment_status_placements')) {
            return;
        }

        $placedIds = fn (string $placement) => DB::table('courier_fulfillment_status_placements')
            ->where('placement', $placement)->pluck('order_status_id')->map(fn ($id) => (int) $id)->all();

        $firstPlaced = function (array $ids, ?string $preferredName): ?int {
            if ($ids === []) {
                return null;
            }
            $q = DB::table('order_statuses')->whereIn('id', $ids);
            if ($preferredName !== null) {
                $named = (clone $q)->where('name', $preferredName)->value('id');
                if ($named) {
                    return (int) $named;
                }
            }
            $first = $q->orderBy('sort_order')->orderBy('name')->value('id');

            return $first ? (int) $first : null;
        };

        $current = fn (string $key) => DB::table('settings')->where('group', 'courier')->where('key', $key)->value('value');

        $pin = function (string $key, ?int $id) use ($current) {
            if ($id === null || $current($key)) {
                return; // nothing was being guessed, or the merchant already chose
            }
            DB::table('settings')->insert([
                'group' => 'courier', 'key' => $key, 'value' => (string) $id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        // The exact chain FulfillmentService::resolveStatusSetting() walked
        // before this migration, evaluated once against this store's data.
        $handover = $placedIds('to_handover');
        $pack = $placedIds('to_pack');
        $pin('booked_order_status_id', $firstPlaced($handover, 'Order Being Shipped'));
        $pin('unbooked_order_status_id', $firstPlaced($pack, null));

        $canceled = DB::table('order_statuses')->where('name', 'Canceled')->orderBy('id')->value('id');
        $pin('cancel_order_status_id', $canceled ? (int) $canceled : null);
    }

    public function down(): void
    {
        // Leaving a pinned choice in place is the safe direction: it is
        // exactly what the store was doing anyway.
    }
};
