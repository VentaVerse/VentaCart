<?php

namespace Extensions\CourierFulfillment\Couriers\Lbc;

use App\Models\Order;
use App\Models\Setting;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;
use Extensions\CourierFulfillment\Support\AnswersUnknownServiceability;

/**
 * LBC Express (a Philippine courier), registered so its credentials have a
 * home BEFORE any adapter exists.
 *
 * GROUND TRUTH for every fact below is the four vendor PDFs committed at
 * `docs/lbc/` (Air Waybill 2026-02-11, Corporate PSGC Lookup 2026-04-22,
 * Pickup Request V2 2026-02-04, Track and Trace V2 2026-04-28), the same
 * convention `docs/spx/` already follows. Nothing here is inferred from a
 * live call: no LBC endpoint has ever been contacted from this codebase.
 *
 * SCOPE, deliberately narrow (owner directive, 2026-08-14: "Create the
 * settings UI first, I'll drop the keys, once drop, you can test it"): this
 * class exists to carry settingsSchema() and nothing else. Every operational
 * method below is an HONEST refusal, never a fake success, and this courier
 * can never be enabled: isEnabled() returns false unconditionally, so
 * CourierManager::enabled() never hands LBC an order and the booking modal
 * renders it in its existing "Not configured" state (an inert card with a
 * settings link) rather than offering a Book button that cannot work.
 *
 * FOUR APIS, FOUR SUBSCRIPTIONS, FOUR KEYS. LBC does not ship one API; it
 * ships four products on one Azure-API-Management-style gateway, each
 * subscribed to separately on the developer portal
 * (https://lbcapiservice.lbcapps.com/) under its own subscription name, and
 * each therefore carrying its OWN subscription key. Owner, 2026-08-14: "We
 * have 4 APIS for LBC. Different architecture. Keys are also separated, for
 * each one."
 *
 *   | API                       | Transport | Portal / subscription name     |
 *   |---------------------------|-----------|--------------------------------|
 *   | Air Waybill               | REST JSON | <CompanyName>_LBCAirwaybill    |
 *   | Pickup Request V2         | XML/HTTP  | CompanyName_LBCPickupRequest   |
 *   | Corporate PSGC Lookup     | REST JSON | CompanyName_LBCCorpPSGCLookup  |
 *   | Track and Trace V2        | SOAP      | CompanyName_TrackandTraceV2    |
 *
 * All four authenticate with the SAME header name, `lbcOAkey`, carrying that
 * subscription's key ("lbcOAkey Use your secret subscription Key", Air
 * Waybill doc, Section 4). Each subscription additionally exposes a Primary
 * and a Secondary key ("To obtain the lbcOAkey, either unhide it or visit
 * your profile. You will find both a Primary and a Secondary Subscription
 * Key there", same doc, Section 5). Those two are interchangeable rotation
 * halves of ONE subscription, not two environments, so one field per API is
 * the correct shape and either half may be pasted into it.
 *
 * NO MODE SELECTOR, and that is a finding rather than an omission. All four
 * PDFs were read for a sandbox/UAT/staging host and none exists: every
 * documented endpoint sits on https://lbcapigateway.lbcapps.com/, the only
 * other host in any of them is the developer portal itself, and the word
 * "sandbox"/"staging"/"UAT" appears nowhere. LBC's own idea of testing is
 * the portal's "Try It" console against that same gateway. A Mode select
 * that only swapped which key was sent would claim an environment split LBC
 * does not make, so the gateway host is a single editable field instead
 * (the docs may go stale; the URL should not need a deploy to fix).
 *
 * NOT BUILT YET, and named here so it is not rediscovered: LBC's
 * `/camsbackend/validate/validateServicableArea` is a real pre-booking
 * serviceability check, and `/camsbackend/{provinces,cities,barangays}/search`
 * is a PSGC-shaped address tree. Between them LBC is the best equipped of the
 * three couriers for checkServiceability(). See this class's own
 * checkServiceability(), which uses the sanctioned `unknown` default until
 * that client exists. Track and Trace is SOAP over .NET/tempuri.org and will
 * need its own client, unlike anything else in this module.
 */
class LbcFulfillment implements CourierFulfillmentInterface
{
    use AnswersUnknownServiceability;

    /**
     * The gateway every documented LBC endpoint sits behind (Air Waybill
     * Section 4's URL REQUEST ENDPOINT, and all four Corporate PSGC Lookup
     * URLs). Editable via `lbc_gateway_url` because a published base URL is
     * exactly the kind of fact a vendor PDF outlives.
     */
    public const DEFAULT_GATEWAY_URL = 'https://lbcapigateway.lbcapps.com/';

    /**
     * The last entry configurationIssues() always appends. Written as a noun
     * phrase because the booking modal prints the list as
     * "Missing {a / b / c}.", see index.blade.php's Not-configured card.
     */
    public const NOT_BUILT = 'a booking adapter, which is not built yet';

    /**
     * What configurationIssues() says instead of naming all five keys when
     * not one of them is stored, which is the state LBC ships in. The
     * booking modal prints that list on a card whose artwork gets whatever
     * room the words leave, so five labels there shrink LBC's mark to half
     * its neighbours' for no gain: "none of them" is one fact, not five.
     */
    public const NO_KEYS_AT_ALL = 'all five LBC keys';

    public function getKey(): string
    {
        return 'lbc';
    }

    /**
     * "LBC", not "LBC Express", which is the name core's own
     * `shipping_couriers` row carries for the MANUAL path. Two cards reading
     * "LBC Express" in one modal, one integrated and one not, would be a
     * puzzle; the shorter name keeps them distinguishable.
     */
    public function getName(): string
    {
        return 'LBC';
    }

    /**
     * Never enabled, whatever the toggle and the keys say. Nothing behind
     * this class can carry a parcel yet, and isEnabled() is the ONE gate
     * every real operation passes through (see CourierManager::enabled()),
     * so returning true on a full credential set would put a Book button in
     * front of an operator that could only fail after they committed.
     *
     * The `lbc_enabled` toggle is still real and still meaningful: it drives
     * CourierManager::flagged(), which is what puts LBC in the booking
     * modal's step 1 AT ALL, as the inert "Not configured" card this method
     * guarantees. Off means LBC is not in that list; on means it is, greyed,
     * with configurationIssues() saying why.
     */
    public function isEnabled(): bool
    {
        return false;
    }

    /**
     * Every subscription key still missing, BY THE NAME OF THE API IT
     * UNLOCKS, plus the fact that no adapter exists yet.
     *
     * Partial configuration is the normal state here, not an error: the keys
     * arrive one subscription approval at a time (LBC approves each
     * separately, 1-2 days per the Air Waybill doc), so this names which
     * ones are outstanding instead of collapsing to one "not configured".
     *
     * Documented deviation from the interface's "field labels only" wording:
     * the final NOT_BUILT entry is not a schema field. A list that named
     * only missing keys would tell an operator LBC starts working the moment
     * the last key lands, which is not true.
     */
    public function configurationIssues(): array
    {
        $stored = Setting::where('group', 'courier')
            ->whereIn('key', array_column($this->credentialFields(), 'key'))
            ->pluck('value', 'key');

        $missing = [];
        foreach ($this->credentialFields() as $field) {
            if (blank($stored[$field['key']] ?? null)) {
                $missing[] = $field['label'];
            }
        }

        // Nothing at all stored: one fact rather than five labels.
        if (count($missing) === count($this->credentialFields()) && $missing !== []) {
            $missing = [self::NO_KEYS_AT_ALL];
        }

        $missing[] = self::NOT_BUILT;

        return $missing;
    }

    /** The schema's encrypted fields, in schema order. @return array<int,array{key:string,label:string}> */
    private function credentialFields(): array
    {
        return array_values(array_filter(
            $this->settingsSchema(),
            fn (array $field) => !empty($field['encrypted'])
        ));
    }

    /**
     * The gateway all four APIs are addressed against, always with a single
     * trailing slash so a caller can concatenate a path without guessing.
     * Nothing calls this yet; it is the seam the four clients will read.
     */
    public function gatewayUrl(): string
    {
        $stored = (string) Setting::where('group', 'courier')->where('key', 'lbc_gateway_url')->value('value');

        return rtrim($stored !== '' ? $stored : self::DEFAULT_GATEWAY_URL, '/') . '/';
    }

    /**
     * The whole point of this class today.
     *
     * Field order is the order the keys are useful in: the toggle, where the
     * calls go, then one key per API in the sequence a booking would use them
     * (validate the address, generate the waybill, request the pickup, track
     * what left). Each key's label names the LBC product it belongs to and
     * its help line names the subscription it is found under, so a key can be
     * matched to a field without guessing.
     */
    public function settingsSchema(): array
    {
        return [
            [
                'key'   => 'lbc_enabled',
                'label' => 'Enable LBC',
                'type'  => 'toggle',
                'help'  => 'Shows LBC on the booking modal. It stays unbookable until the adapter is built.',
            ],
            [
                'key'         => 'lbc_gateway_url',
                'label'       => 'API gateway',
                'type'        => 'text',
                'placeholder' => self::DEFAULT_GATEWAY_URL,
            ],
            [
                'key'       => 'lbc_psgc_key',
                'label'     => 'Corporate PSGC Lookup key',
                'type'      => 'secret',
                'encrypted' => true,
                'help'      => 'Subscription CompanyName_LBCCorpPSGCLookup, primary or secondary key.',
            ],
            [
                'key'       => 'lbc_airwaybill_key',
                'label'     => 'Air Waybill key',
                'type'      => 'secret',
                'encrypted' => true,
                'help'      => 'Subscription <CompanyName>_LBCAirwaybill, primary or secondary key.',
            ],
            [
                'key'       => 'lbc_pickup_key',
                'label'     => 'Pickup Request key',
                'type'      => 'secret',
                'encrypted' => true,
                'help'      => 'Subscription CompanyName_LBCPickupRequest, primary or secondary key.',
            ],
            [
                'key'       => 'lbc_track_key',
                'label'     => 'Track and Trace key',
                'type'      => 'secret',
                'encrypted' => true,
                'help'      => 'Subscription CompanyName_TrackandTraceV2, primary or secondary key.',
            ],
            [
                // Not a subscription key and not sent as one: the Pickup
                // Request body carries its own <AppKey> node (a 32-character
                // value in the doc's sample payload) alongside the lbcOAkey
                // header. It is a credential with nowhere else to live, so it
                // gets a field of its own rather than being discovered
                // missing on the first pickup request.
                'key'       => 'lbc_pickup_app_key',
                'label'     => 'Pickup Request AppKey',
                'type'      => 'secret',
                'encrypted' => true,
                'help'      => 'The <AppKey> node in the Pickup Request body, not a subscription key.',
            ],
        ];
    }

    // --- Everything below is honest refusal until the adapters exist -------

    /** No booking form is reachable (isEnabled() is false), so there is nothing truthful to list. */
    public function getServiceTypes(): array
    {
        return [];
    }

    public function getParcelOptions(): array
    {
        return [];
    }

    public function getShippingPaymentOptions(): array
    {
        return [];
    }

    public function createShipment(Order $order, array $opts): array
    {
        return ['success' => false, 'error' => $this->notBuilt('Booking')];
    }

    public function cancelShipment(string $trackingNumber): array
    {
        return ['success' => false, 'error' => $this->notBuilt('Cancelling a booking')];
    }

    /** LBC creates nothing yet, so no order can be sitting in courier_status='creating' for it. */
    public function resolvePendingCreation(Order $order): ?string
    {
        return null;
    }

    public function getTracking(string $trackingNumber): array
    {
        return ['success' => false, 'error' => $this->notBuilt('Tracking')];
    }

    public function terminalStatuses(): array
    {
        return [];
    }

    public function cancelableStatuses(): array
    {
        return [];
    }

    /**
     * Unreachable: FulfillmentService::phaseFor() short-circuits to `to_pack`
     * when no tracking number exists, and no LBC booking can exist. The
     * interface forbids returning `to_pack` here and offers no "no opinion"
     * value, so this returns the most neutral real bucket rather than
     * inventing a state: it describes nothing, because there is nothing.
     */
    public function statusPhase(?string $status): array
    {
        return ['phase' => 'to_handover', 'step' => 1, 'failed' => false];
    }

    /** LBC's rate card is not wired, and a figure nobody quoted is worse than no figure. */
    public function estimateFee(Order $order, array $options): ?object
    {
        return null;
    }

    /*
     * checkServiceability() is inherited from AnswersUnknownServiceability.
     * LBC publishes a real coverage endpoint (/camsbackend/validate/
     * validateServicableArea, which answers "Serviceable"/"Non-Serviceable"
     * plus a rate and a rate category for a PSGC code), but nothing calls it
     * yet, and `unknown` is the sanctioned way to say "we did not ask".
     */

    /** LBC's pickup scheduling lives in the Pickup Request API, which is not wired. */
    public function getPickupSlots(Order $order): array
    {
        return [];
    }

    public function printLabel(string $trackingNumber, string $format): array
    {
        return ['success' => false, 'error' => $this->notBuilt('Printing a waybill')];
    }

    /** No webhook is registered for LBC, and an event list invented here would be fiction. */
    public function parseWebhook(array $payload): array
    {
        return [];
    }

    public function trackingLinks(Order $order): array
    {
        return [];
    }

    /**
     * Deliberately makes NO outbound call. There is no LBC client to test
     * with, and a request built here would be guesswork against a live
     * gateway using the owner's real subscription quota.
     */
    public function testConnection(): array
    {
        return ['ok' => false, 'message' => 'Nothing to test yet: LBC has settings but no client.'];
    }

    public function refreshStatusLabels(): array
    {
        return ['ok' => false, 'error' => $this->notBuilt('Reading LBC statuses')];
    }

    /**
     * Empty on purpose, which hides the status-mapping table on this
     * courier's settings page entirely (see settings-courier.blade.php).
     * LBC's vocabulary IS documented (Track and Trace V2's Table 6.0 gate
     * status IDs), but nothing reads a status map until the SOAP tracking
     * client exists, and a table that persists choices nothing acts on is
     * configuration theatre.
     */
    public function mappableStatuses(): array
    {
        return [];
    }

    private function notBuilt(string $what): string
    {
        return $what . ' with LBC is not implemented yet.';
    }
}
