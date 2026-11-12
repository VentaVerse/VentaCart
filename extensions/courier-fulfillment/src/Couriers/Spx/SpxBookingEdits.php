<?php

namespace Extensions\CourierFulfillment\Couriers\Spx;

use App\Models\Order;
use App\Models\OrderHistory;
use Extensions\CourierFulfillment\Support\CourierRefusal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Updating an SPX booking (1.7 Update Order) while SPX still holds the parcel
 * as 1001 Pending Pickup, at most config('courier-fulfillment.spx.booking_edits_max')
 * times. What goes again is the order's current recipient and address (as
 * corrected on the order in the admin) plus the edited parcel and pickup
 * window. Who pays shipping, the collect type, the COD amount and the
 * insured value go back exactly as booked; the tracking number does not
 * change.
 *
 * SPX is asked first: a refusal changes nothing here. After an update the
 * stored waybill is deleted, so the next print fetches SPX's new one.
 */
final class SpxBookingEdits
{
    private const LOCK_SECONDS = 120;

    public function __construct(private readonly ?SpxFulfillment $courier = null)
    {
    }

    public static function max(): int
    {
        return max(0, (int) config('courier-fulfillment.spx.booking_edits_max', 3));
    }

    public static function made(Order $order): int
    {
        $updates = self::meta($order)['booking_updates'] ?? null;

        return is_array($updates) ? count($updates) : 0;
    }

    public static function left(Order $order): int
    {
        return max(0, self::max() - self::made($order));
    }

    public static function allowed(Order $order): bool
    {
        return (string) $order->courier_provider === 'spx'
            && (string) $order->courier_status === SpxFulfillment::PENDING_PICKUP
            && trim((string) $order->courier_tracking_number) !== ''
            && is_string(self::meta($order)['reference_id'] ?? null)
            && self::left($order) > 0;
    }

    /**
     * Sends the edited booking to SPX and, when SPX takes it, records it.
     * Null on success, else why not.
     *
     * @param array<string, mixed> $edits parcel, weight_kg, length_cm, width_cm, height_cm, pickup_slot
     */
    public function update(Order $order, array $edits, ?int $actorUserId = null): ?CourierRefusal
    {
        $lock = Cache::lock("courier-fulfillment:order:{$order->id}", self::LOCK_SECONDS);
        if (!$lock->get()) {
            return new CourierRefusal('This order is busy', 'Try again in a moment.');
        }

        try {
            $fresh = $order->fresh();
            if ($fresh === null || !self::allowed($fresh)) {
                return new CourierRefusal('This booking can no longer be edited', self::whyNot($fresh));
            }

            $answer = ($this->courier ?? new SpxFulfillment())->updateBooking($fresh, $edits);
            if (($answer['ok'] ?? false) !== true) {
                if (($answer['raw'] ?? null) !== null) {
                    Log::info('SPX refused a booking update', ['order' => $fresh->id, 'courier_said' => $answer['raw']]);
                }

                return $answer['refusal'];
            }

            $this->store($fresh, $answer['payload'] ?? [], $actorUserId);

            return null;
        } finally {
            $lock->release();
        }
    }

    private static function whyNot(?Order $order): string
    {
        if ($order === null || (string) $order->courier_provider !== 'spx' || trim((string) $order->courier_tracking_number) === '') {
            return 'It is not an SPX booking.';
        }
        if ((string) $order->courier_status !== SpxFulfillment::PENDING_PICKUP) {
            return 'SPX only takes changes while the parcel is pending pickup.';
        }
        if (self::left($order) === 0) {
            return 'SPX takes at most ' . self::max() . ' changes to one booking.';
        }

        return 'It has no SPX reference on file.';
    }

    private function store(Order $order, array $payload, ?int $actorUserId): void
    {
        $tracking = (string) $order->courier_tracking_number;

        try {
            DB::transaction(function () use ($order, $payload, $tracking, $actorUserId) {
                $row = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                $meta = self::meta($row);
                $updates = is_array($meta['booking_updates'] ?? null) ? array_values($meta['booking_updates']) : [];
                $updates[] = ['at' => now()->toIso8601String(), 'by' => $actorUserId];
                $meta['booking_updates'] = $updates;

                $parcel = $payload['parcel_info'] ?? [];
                foreach (['parcel_weight' => 'weight_kg', 'parcel_length' => 'length_cm', 'parcel_width' => 'width_cm', 'parcel_height' => 'height_cm'] as $from => $to) {
                    if (isset($parcel[$from])) {
                        $meta[$to] = $parcel[$from];
                    }
                }
                $fulfillment = $payload['fulfillment_info'] ?? [];
                if (($fulfillment['collect_type'] ?? null) === 1) {
                    $meta['pickup_slot'] = json_encode([
                        'pickup_time'          => (int) $fulfillment['pickup_time'],
                        'pickup_time_range_id' => (int) $fulfillment['pickup_time_range_id'],
                        'pickup_time_range'    => (string) $fulfillment['pickup_time_range'],
                    ]);
                }

                $row->forceFill(['courier_meta' => json_encode($meta)])->save();

                OrderHistory::create([
                    'order_id'        => $row->id,
                    'order_status_id' => $row->order_status_id,
                    'comment'         => "SPX booking {$tracking} updated (" . count($updates) . ' of ' . self::max() . ' changes). Print the waybill again.',
                    'notify_customer' => false,
                ]);
            });
        } catch (\Throwable $e) {
            // SPX has the update; what it holds is now the truth.
            Log::critical('SPX took a booking update that could not be saved', ['order' => $order->id, 'exception' => $e::class]);

            throw $e;
        }

        // The waybill changes with the order: the stored one goes, and the next print fetches the new one.
        Storage::disk('local')->delete(SpxFulfillment::labelStoragePath($tracking));
    }

    /** @return array<string, mixed> */
    private static function meta(?Order $order): array
    {
        $meta = $order?->courier_meta ? json_decode((string) $order->courier_meta, true) : null;

        return is_array($meta) ? $meta : [];
    }
}
