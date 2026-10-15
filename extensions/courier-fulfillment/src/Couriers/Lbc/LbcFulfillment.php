<?php

namespace Extensions\CourierFulfillment\Couriers\Lbc;

use App\Models\Order;
use App\Models\Setting;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;
use Extensions\CourierFulfillment\Support\AnswersUnknownServiceability;
use Illuminate\Support\Facades\Crypt;

/**
 * LBC Express (a Philippine courier), registered so its credentials have a
 * home BEFORE any adapter exists.
 *
 * GROUND TRUTH is `docs/lbc/integration-notes.md`, written from a live
 * exercise of all four LBC APIs on 2026-08-14 with the owner's real keys.
 * The four vendor PDFs beside it are still the reference for payload shapes,
 * but where a PDF and the live gateway disagree the gateway wins, and this
 * class follows the notes. Three claims of the first build were disproven
 * there and are corrected below: the auth header is NOT uniform, the
 * environment split IS real, and the Pickup AppKey is NOT required.
 *
 * SCOPE, still deliberately narrow: this class carries settingsSchema() and
 * the constants a future client will read, and nothing else. Every
 * operational method below is an HONEST refusal, never a fake success, and
 * this courier can never be enabled: isEnabled() returns false
 * unconditionally, so CourierManager::enabled() never hands LBC an order and
 * the booking modal renders it in its existing "Not configured" state (an
 * inert card with a settings link) rather than offering a Book button that
 * cannot work. Nothing in this class makes an outbound call.
 *
 * FOUR APIS, FOUR SUBSCRIPTIONS, EIGHT KEYS. LBC ships four products on one
 * Azure-API-Management-style gateway, each subscribed to separately on the
 * developer portal (https://lbcapiservice.lbcapps.com/) as
 * `<CompanyName>_<ApiName>`, and each carrying its own subscription key:
 *
 *   | API                          | Transport | Auth header                  |
 *   |------------------------------|-----------|------------------------------|
 *   | Common API (address, cover)  | REST JSON | lbcOAkey                     |
 *   | Air Waybill                  | REST JSON | lbcOAkey                     |
 *   | Pickup Request V2            | XML/HTTP  | Ocp-Apim-Subscription-Key    |
 *   | Track and Trace V2           | SOAP      | lbcOAkey                     |
 *
 * See AUTH_HEADERS below: the header is per API, and sending the wrong one
 * answers `401 "Access denied due to missing subscription key"`, which reads
 * like a bad key rather than a wrong header name.
 *
 * TEST VERSUS LIVE IS A KEY, NOT A HOST. There is no sandbox, UAT or staging
 * gateway (the words appear in none of the four PDFs and every documented
 * endpoint sits on lbcapigateway.lbcapps.com). LBC instead issues a PRIMARY
 * and a SECONDARY key per subscription and treats the secondary as test, so
 * the environment is chosen by which key is sent. That is a genuine split,
 * so the page carries a Mode select and each API holds two keys, the same
 * mode_scoped shape QuadX and SPX already use. The first build read the
 * primary/secondary pair as Azure's rotation halves and omitted the mode;
 * the vendor's own convention overrules that reading.
 *
 * THE TRACKING NUMBER IS OURS TO ISSUE, unlike every other courier here.
 * The Air Waybill data dictionary calls TrackingNo the "LBC Tracking Number
 * series from sales assigned to clients": LBC hands a block to the merchant
 * and the merchant allocates from it, so booking does not return a number,
 * it consumes one. TRACKING_SERIES_KEYS below holds the assigned range, and
 * configurationIssues() names it outstanding until it is filled in. The
 * ALLOCATOR itself (issue the next unused number, never reuse one, warn
 * before the block runs out) is persistent state and belongs with the
 * adapter, not here.
 *
 * NOT BUILT YET, and named here so it is not rediscovered: LBC's
 * `/camsbackend/validate/validateServicableArea` is a real pre-booking
 * serviceability check that answers status, rate and rate category for a
 * PSGC code, and `/camsbackend/{provinces,cities,barangays}/search` is the
 * fuzzy resolver that produces that code. Between them LBC is the best
 * equipped of the three couriers for checkServiceability(). See this class's
 * own checkServiceability(), which uses the sanctioned `unknown` default
 * until that client exists. Track and Trace is SOAP over .NET/tempuri.org
 * and will need its own client, unlike anything else in this module.
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
     * THE AUTH HEADER IS PER API, and this map is the only place that fact
     * is allowed to live. Verified live on 2026-08-14 (see
     * docs/lbc/integration-notes.md): Pickup Request authenticates on
     * `Ocp-Apim-Subscription-Key`, the other three on `lbcOAkey`, and each
     * rejects the other's header with
     * `401 "Access denied due to missing subscription key"`, a message that
     * names the key, not the header, so a wrong header costs an afternoon.
     * Read it through authHeader(); never hardcode either string in a client.
     */
    public const AUTH_HEADERS = [
        'common'     => 'lbcOAkey',
        'airwaybill' => 'lbcOAkey',
        'pickup'     => 'Ocp-Apim-Subscription-Key',
        'track'      => 'lbcOAkey',
    ];

    /**
     * The two environments, which LBC selects by WHICH KEY is sent rather
     * than by host. `test` first, because it is the safe default for a store
     * that has not been approved for live traffic yet and because the
     * settings page falls back to the mode select's first option.
     */
    public const MODES = ['test', 'live'];

    public const DEFAULT_MODE = 'test';

    /** The assigned tracking-number block, both ends, in schema order. */
    public const TRACKING_SERIES_KEYS = ['lbc_tracking_series_start', 'lbc_tracking_series_end'];

    /**
     * The last entry configurationIssues() always appends. Written as a noun
     * phrase because the booking modal prints the list as
     * "Missing {a / b / c}.", see index.blade.php's Not-configured card.
     */
    public const NOT_BUILT = 'a booking adapter, which is not built yet';

    /**
     * What configurationIssues() says instead of naming all four of the
     * active mode's keys when not one of them is stored, which is the state
     * LBC ships in. The booking modal prints that list on a card whose
     * artwork gets whatever room the words leave, so four labels there
     * shrink LBC's mark for no gain: "none of them" is one fact, not four.
     */
    public const NO_KEYS_AT_ALL = 'all four LBC keys';

    /**
     * LBC does not issue tracking numbers; sales assigns the merchant a
     * block and the merchant allocates from it. Until that block is on file
     * nothing can be booked, and no amount of correct credentials changes
     * that, so it is named separately from the keys.
     */
    public const NO_TRACKING_SERIES = 'a tracking number series from LBC sales';

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
     * Everything still standing between this store and a first LBC booking:
     * the ACTIVE MODE's missing subscription keys by the name of the API
     * each unlocks, the two merchant constants the Pickup payload demands,
     * the tracking-number series LBC sales has not assigned yet, and the
     * fact that no adapter exists.
     *
     * Scoped to the active mode on purpose: the live and test halves carry
     * the same four labels, so listing both would name "Air Waybill key"
     * twice and tell an operator nothing about which one to go and get.
     *
     * Partial configuration is the normal state here, not an error: the keys
     * arrive one subscription approval at a time (LBC approves each
     * separately, 1-2 days per the Air Waybill doc), so this names which
     * ones are outstanding instead of collapsing to one "not configured".
     *
     * Documented deviation from the interface's "field labels only" wording:
     * NO_TRACKING_SERIES and NOT_BUILT are not schema field labels. A list
     * that named only missing settings would tell an operator LBC starts
     * working the moment the last box is filled, which is not true.
     */
    public function configurationIssues(): array
    {
        $fields = array_merge($this->credentialFields(), $this->merchantFields());

        $stored = Setting::where('group', 'courier')
            ->whereIn('key', array_merge(
                array_column($fields, 'key'),
                self::TRACKING_SERIES_KEYS
            ))
            ->pluck('value', 'key');

        $missingKeys = [];
        foreach ($this->credentialFields() as $field) {
            if (blank($stored[$field['key']] ?? null)) {
                $missingKeys[] = $field['label'];
            }
        }

        // Nothing at all stored: one fact rather than four labels.
        if (count($missingKeys) === count($this->credentialFields()) && $missingKeys !== []) {
            $missingKeys = [self::NO_KEYS_AT_ALL];
        }

        $missing = $missingKeys;

        // The merchant's own identifiers. Not secrets, but LBC refuses a
        // pickup without them (error 152 "Client is required"), so they are
        // as blocking as any key.
        foreach ($this->merchantFields() as $field) {
            if (blank($stored[$field['key']] ?? null)) {
                $missing[] = $field['label'];
            }
        }

        // Either end of the block missing means no number can be allocated,
        // and a half-known range is not a range.
        foreach (self::TRACKING_SERIES_KEYS as $key) {
            if (blank($stored[$key] ?? null)) {
                $missing[] = self::NO_TRACKING_SERIES;
                break;
            }
        }

        $missing[] = self::NOT_BUILT;

        return $missing;
    }

    /**
     * The ACTIVE MODE's encrypted fields, in schema order: the four
     * subscription keys that mode would send. @return array<int,array{key:string,label:string}>
     */
    private function credentialFields(): array
    {
        $mode = $this->mode();

        return array_values(array_filter(
            $this->settingsSchema(),
            fn (array $field) => !empty($field['encrypted'])
                && (($field['mode_scoped'] ?? null) === null || $field['mode_scoped'] === $mode)
        ));
    }

    /**
     * The merchant constants LBC assigns and the Pickup payload requires:
     * `Client` (error 152, sample "TEST") and the shipper account number
     * (sample "2015062300003"). Neither is a credential, since they identify
     * the account rather than authenticate it, so both are plain text fields,
     * and both are mandatory for booking. @return array<int,array{key:string,label:string}>
     */
    private function merchantFields(): array
    {
        return array_values(array_filter(
            $this->settingsSchema(),
            fn (array $field) => in_array($field['key'], ['lbc_client_code', 'lbc_shipper_account_no'], true)
        ));
    }

    /**
     * Which set of keys this store sends. LBC has one gateway for both
     * environments and splits them by key: the secondary key of each
     * subscription is the test one. An unrecognised stored value falls back
     * to `test` rather than quietly going live.
     */
    public function mode(): string
    {
        $stored = (string) Setting::where('group', 'courier')->where('key', 'lbc_mode')->value('value');

        return in_array($stored, self::MODES, true) ? $stored : self::DEFAULT_MODE;
    }

    /**
     * The header name this API authenticates on. See AUTH_HEADERS: it is not
     * uniform, and the 401 it returns for the wrong one names the key rather
     * than the header.
     *
     * @throws \InvalidArgumentException for an API this class does not know,
     *         which is a programming error rather than a configuration one:
     *         guessing a header would produce that same misleading 401.
     */
    public function authHeader(string $api): string
    {
        return self::AUTH_HEADERS[$api]
            ?? throw new \InvalidArgumentException('Unknown LBC API: ' . $api);
    }

    /** The settings key holding one API's subscription key for the given mode (default: the active one). */
    public function keySetting(string $api, ?string $mode = null): string
    {
        if (!array_key_exists($api, self::AUTH_HEADERS)) {
            throw new \InvalidArgumentException('Unknown LBC API: ' . $api);
        }

        $mode ??= $this->mode();
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException('Unknown LBC mode: ' . $mode);
        }

        return 'lbc_' . $mode . '_' . $api . '_key';
    }

    /**
     * One API's subscription key for the active mode, decrypted, or null
     * when it is unset or undecryptable (a key encrypted under a previous
     * APP_KEY). The seam the four clients will read; nothing sends it yet.
     */
    public function subscriptionKey(string $api): ?string
    {
        $stored = (string) Setting::where('group', 'courier')
            ->where('key', $this->keySetting($api))
            ->value('value');

        if ($stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The gateway all four APIs are addressed against, always with a single
     * trailing slash so a caller can concatenate a path without guessing.
     * One host for both modes: the environment is the key, not the URL.
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
     * Order is the order the values are useful in: the toggle, the mode,
     * where the calls go, who LBC thinks we are, which numbers are ours to
     * use, and then one key per API, twice, once per mode, in the sequence
     * a booking would use them (resolve and check the address, generate the
     * waybill, request the pickup, track what left). Each key's label names
     * the LBC product it unlocks; which environment it belongs to is said
     * once, by the group it sits in, rather than repeated on every field.
     *
     * The four `lbc_{mode}_{api}_key` names are built by keySetting(), so a
     * client never spells one out.
     *
     * NO AppKey field. The Pickup sample payload carries an <AppKey> node
     * and the first build inferred a required credential from it; a pickup
     * was then created successfully with that node removed entirely,
     * authenticating on the header alone. It was an older body-based auth
     * style, superseded, and a field for it would be a box nobody can fill
     * correctly.
     */
    public function settingsSchema(): array
    {
        return [
            [
                'key'   => 'lbc_enabled',
                'label' => 'Enable LBC',
                'type'  => 'toggle',
                'hint'  => 'Puts LBC on the booking modal. It stays unbookable until the adapter is built.',
            ],
            [
                'key'     => 'lbc_mode',
                'label'   => 'Mode',
                'type'    => 'select',
                'options' => [
                    ['value' => 'test', 'label' => 'Test'],
                    ['value' => 'live', 'label' => 'Live'],
                ],
                // The one consequence nothing on this page can say in its own
                // name: LBC has a single gateway, and the environment is
                // decided by which of a subscription's two keys is sent.
                'hint'    => 'LBC has one gateway for both. It issues a primary and a secondary key per subscription and treats the secondary as the test one, so the mode decides which key is sent.',
            ],
            [
                'key'         => 'lbc_gateway_url',
                'label'       => 'API gateway',
                'type'        => 'text',
                'placeholder' => self::DEFAULT_GATEWAY_URL,
            ],
            [
                // Pickup Request error 152, "Client is required". An
                // identifier LBC assigns the merchant; the doc's sample is
                // the literal string TEST.
                'key'   => 'lbc_client_code',
                'label' => 'Client code',
                'type'  => 'text',
            ],
            [
                // The merchant's LBC account number, carried on every pickup
                // (doc sample 2015062300003).
                'key'   => 'lbc_shipper_account_no',
                'label' => 'Shipper Account No',
                'type'  => 'text',
            ],
            [
                // LBC issues no tracking numbers: sales assigns a block and
                // the merchant allocates from it. Both ends are held here;
                // the allocator that consumes them is adapter work.
                'key'   => self::TRACKING_SERIES_KEYS[0],
                'label' => 'Tracking series first number',
                'type'  => 'text',
            ],
            [
                'key'   => self::TRACKING_SERIES_KEYS[1],
                'label' => 'Tracking series last number',
                'type'  => 'text',
            ],
            [
                'key'         => 'lbc_test_common_key',
                'label'       => 'Common API key',
                'type'        => 'secret',
                'encrypted'   => true,
                'mode_scoped' => 'test',
            ],
            [
                'key'         => 'lbc_test_airwaybill_key',
                'label'       => 'Air Waybill key',
                'type'        => 'secret',
                'encrypted'   => true,
                'mode_scoped' => 'test',
            ],
            [
                'key'         => 'lbc_test_pickup_key',
                'label'       => 'Pickup Request key',
                'type'        => 'secret',
                'encrypted'   => true,
                'mode_scoped' => 'test',
            ],
            [
                'key'         => 'lbc_test_track_key',
                'label'       => 'Track and Trace key',
                'type'        => 'secret',
                'encrypted'   => true,
                'mode_scoped' => 'test',
            ],
            [
                'key'         => 'lbc_live_common_key',
                'label'       => 'Common API key',
                'type'        => 'secret',
                'encrypted'   => true,
                'mode_scoped' => 'live',
            ],
            [
                'key'         => 'lbc_live_airwaybill_key',
                'label'       => 'Air Waybill key',
                'type'        => 'secret',
                'encrypted'   => true,
                'mode_scoped' => 'live',
            ],
            [
                'key'         => 'lbc_live_pickup_key',
                'label'       => 'Pickup Request key',
                'type'        => 'secret',
                'encrypted'   => true,
                'mode_scoped' => 'live',
            ],
            [
                'key'         => 'lbc_live_track_key',
                'label'       => 'Track and Trace key',
                'type'        => 'secret',
                'encrypted'   => true,
                'mode_scoped' => 'live',
            ],
        ];
    }

    // --- Everything below is honest refusal until the adapters exist -------

    /** No booking form is reachable (isEnabled() is false), so there is nothing truthful to list. */
    public function getServiceTypes(): array
    {
        return [];
    }

    /**
     * LBC's packaging products, as the packer names them.
     *
     * The value is the PKG code that goes into the payload verbatim, from
     * Table 2.0 "PKG or PRODUCT ID LIST" on page 15 of
     * LBC_Pickup_Request_API_V2 2026-02-04.pdf. Every row in that table
     * carries ProductLineID 2, so the CARGO / DOCUMENTS split is descriptive
     * and PKG alone selects the product.
     *
     * Only the products this store actually ships are listed (owner ruling
     * 2026-09-09), not LBC's whole catalogue: PLASTIC, CRATE, PIECE and SACK
     * are packaging shapes for goods NGD does not send, and offering them is
     * a way to mis-book rather than a feature. Kilobox is shortened to KB
     * because the packer reads this list twenty times an afternoon.
     *
     * Own packaging is PKG 16 (BOX), LBC's code for a parcel that fits none
     * of their standard sizes. It is the one option whose dimensions cannot
     * be defaulted, because there is no standard box behind it.
     *
     * NOT LISTED YET: N-Pack Sakto. The owner ships it, but it does not
     * appear in Table 2.0 under that name, and guessing which of the four
     * unclaimed pouch codes (3 N-POUCH REGULAR, 9 X POUCH, 61 N-POUCH XL,
     * 62 N-POUCH SS) it maps to would silently book the wrong product.
     * Pending LBC confirming the code.
     */
    public function getParcelOptions(): array
    {
        return [
            ['value' => '45',  'label' => 'N-Pack Small'],
            ['value' => '8',   'label' => 'N-Pack Large'],
            ['value' => '165', 'label' => 'KB Mini'],
            ['value' => '6',   'label' => 'KB Small'],
            ['value' => '164', 'label' => 'KB Slim'],
            ['value' => '63',  'label' => 'KB Medium'],
            ['value' => '7',   'label' => 'KB Large'],
            ['value' => '64',  'label' => 'KB XL'],
            ['value' => '16',  'label' => 'Own packaging'],
        ];
    }

    /** The PKG code for a parcel the store packs itself, so its dimensions are asked for. */
    public const OWN_PACKAGING_PKG = '16';

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
