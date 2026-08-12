<?php

namespace Extensions\CourierFulfillment;

use App\Models\Order;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;
use Extensions\CourierFulfillment\Support\ServiceabilityVerdict;
use Illuminate\Support\Facades\Log;

/**
 * Asks every configured courier, before anything is booked, whether it will
 * carry this order's parcel to this order's address.
 *
 * The booking modal BLOCKS on this (owner ruling, 2026-08-12: all couriers
 * resolved before any card is drawn, rather than cards that change under the
 * operator's hand), which makes two protections structural rather than nice
 * to have:
 *
 *  1. TIMEOUT_SECONDS is a hard per-courier budget. Each adapter bounds its
 *     own HTTP call to it, and this class independently discards any verdict
 *     that arrived late. A courier that took longer than the budget has not
 *     answered in time, and "answered too late" is `unknown`, never a
 *     refusal.
 *  2. Every throwable is caught per courier. One adapter blowing up resolves
 *     to `unknown` for that courier alone and never takes the modal with it.
 *
 * Manual ("another courier, recorded by hand") is a branch in
 * FulfillmentService, not a registered courier, so it is never asked: nothing
 * is quoted and nothing is sent, so there is nothing to answer.
 *
 * The verdict cache lives in the SESSION and nowhere else. Coverage moves,
 * and a stale refusal persisted against an address is worse than an extra
 * call: the operator would be told a courier refuses somewhere it now serves,
 * with no way to find out otherwise.
 */
class ServiceabilityService
{
    /**
     * The hard per-courier budget, in seconds. One slow courier must never
     * hold the packing desk: anything slower than this resolves to
     * `Could not check`, which is still clickable, so the operator can go
     * and ask the courier directly by booking.
     */
    public const TIMEOUT_SECONDS = 5;

    /**
     * Where the per-session verdicts live.
     *
     * The version suffix is load-bearing: a verdict is stored as a plain
     * array, and an operator whose session predates a change to that shape
     * would otherwise be served a stale entry missing the newer keys (the
     * first one, `kind`, decides whether the modal writes its own sentence or
     * prints the courier's words). Bump it whenever the stored shape changes,
     * which costs one extra call per address and nothing else.
     */
    private const SESSION_KEY = 'courier_fulfillment.serviceability.v2';

    /**
     * Bound on the session cache. A packing session works through tens of
     * orders, not thousands, and an unbounded map in the session payload is
     * a slow leak nobody would ever notice.
     */
    private const SESSION_LIMIT = 60;

    /**
     * Every configured courier's verdict for this order, keyed by courier.
     *
     * CONFIGURED couriers only (CourierManager::enabled()): a courier whose
     * credentials are missing cannot be asked at all, and its card already
     * says "Not configured" with the way to fix it, and asking it would produce
     * a second, less useful refusal on top of the real one.
     *
     * @return array<string, ServiceabilityVerdict>
     */
    public static function forOrder(Order $order): array
    {
        $verdicts = [];
        foreach (CourierManager::enabled() as $courier) {
            $verdicts[$courier->getKey()] = self::forCourier($courier, $order);
        }

        return $verdicts;
    }

    /** One courier, cached for this session, bounded by the timeout budget. */
    public static function forCourier(CourierFulfillmentInterface $courier, Order $order): ServiceabilityVerdict
    {
        $key = self::cacheKey($courier->getKey(), $order);

        // Read the whole map and index it here rather than reaching in with
        // a dotted path: the entry keys are courier:hash pairs, and the
        // session helper would read a dot inside one as another level.
        $all = session(self::SESSION_KEY, []);
        $cached = is_array($all) ? ($all[$key] ?? null) : null;
        if (is_array($cached)) {
            return ServiceabilityVerdict::fromArray($cached);
        }

        $startedAt = microtime(true);

        try {
            $verdict = $courier->checkServiceability($order);
        } catch (\Throwable $e) {
            // An adapter that threw told us nothing about the address.
            Log::warning('Serviceability check failed', [
                'courier' => $courier->getKey(), 'order' => $order->id, 'error' => $e->getMessage(),
            ]);
            $verdict = ServiceabilityVerdict::unknown(ServiceabilityVerdict::KIND_THEIR_SIDE);
        }

        // The budget is enforced HERE as well as inside the adapter, because
        // an adapter is free to make more than one call, or to use a client
        // that does not honour the budget it was handed. A verdict that
        // arrived after the budget is discarded rather than trusted: the
        // operator has already waited too long, and the honest report of
        // that is "could not check".
        if ((microtime(true) - $startedAt) > self::TIMEOUT_SECONDS) {
            $verdict = ServiceabilityVerdict::unknown(ServiceabilityVerdict::KIND_UNREACHABLE);
        }

        self::remember($key, $verdict);

        return $verdict;
    }

    /**
     * Did every courier we could actually ask refuse? The modal prints one
     * plain line when this is true. False when there was nobody to ask: no
     * courier means no refusal.
     *
     * @param array<string, ServiceabilityVerdict> $verdicts
     */
    public static function allRefused(array $verdicts): bool
    {
        if ($verdicts === []) {
            return false;
        }

        foreach ($verdicts as $verdict) {
            if (!$verdict->isRefusal()) {
                return false;
            }
        }

        return true;
    }

    /**
     * The address this verdict is about, in the customer's own words. Two
     * orders to the same place share one answer, and editing the address
     * invalidates it by producing a different key.
     */
    private static function cacheKey(string $courierKey, Order $order): string
    {
        $fingerprint = strtolower(trim(implode('|', [
            $order->shipping_address_1,
            $order->shipping_barangay,
            $order->shipping_district,
            $order->shipping_city,
            $order->shipping_state,
            $order->shipping_postcode,
            $order->shipping_country_id,
        ])));

        return $courierKey . ':' . sha1($fingerprint);
    }

    private static function remember(string $key, ServiceabilityVerdict $verdict): void
    {
        $all = session(self::SESSION_KEY, []);
        if (!is_array($all)) {
            $all = [];
        }

        // Oldest out first, so a long packing session cannot grow the
        // session payload without limit.
        unset($all[$key]);
        $all[$key] = $verdict->toArray();
        if (count($all) > self::SESSION_LIMIT) {
            $all = array_slice($all, -self::SESSION_LIMIT, null, true);
        }

        session([self::SESSION_KEY => $all]);
    }

    /** Drop every remembered verdict. Used when a booking proves one wrong. */
    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
