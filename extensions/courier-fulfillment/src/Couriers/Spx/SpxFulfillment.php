<?php

namespace Extensions\CourierFulfillment\Couriers\Spx;

use App\Models\Order;
use App\Models\Setting;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;
use Extensions\CourierFulfillment\Contracts\ProvidesPublicTrackingUrl;
use Extensions\CourierFulfillment\Contracts\ReportsShippingCost;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\ServiceabilityService;
use Extensions\CourierFulfillment\Support\CourierRefusal;
use Extensions\CourierFulfillment\Support\OrderWeight;
use Extensions\CourierFulfillment\Support\PhPhone;
use Extensions\CourierFulfillment\Support\ServiceabilityVerdict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SPX (Shopee Xpress PH) courier. Endpoint numbers in the docblocks (1.4,
 * 2.1, ...) are SPX's own Open API section numbers.
 *
 * How a booking is built and sent:
 *  - Sender and delivery addresses are placed on SPX's own location list
 *    (SpxLocations). SPX's state/city/district/street are island group,
 *    province, city and barangay, not PSGC's levels, and an address SPX does
 *    not list is refused before anything is sent. The list also says where
 *    SPX delivers, collects COD and picks up; those flags are checked too.
 *  - The parcel weight is the real one: what the merchant typed, else the
 *    products' own weights. A pouch preset supplies the size and a weight
 *    ceiling, never the weight. Typed sizes beat the preset's.
 *  - Before 1.4 Create Order, 2.1 Check Shipping Fee is asked with the same
 *    body: it applies the same parcel rules as create and creates nothing,
 *    so a parcel SPX would refuse is refused before a booking exists.
 *  - Every booking gets its own SPX order_id (the date plus six random
 *    characters), stored as the booking's reference_id. It is never the
 *    store's order id: one order can be booked, cancelled and booked again.
 *  - Create is asynchronous (1.4 gives a batch number, 1.5 settles it). A
 *    transport failure or an SPX server error means the outcome is unknown:
 *    the booking is kept as `creating` with outcome_unknown, never retried
 *    blindly. A later attempt reuses the same order_id, so SPX answers 13103
 *    (already used) instead of creating a second parcel, and the existing
 *    order is looked up.
 *  - Collection is explicit: `method` pickup or dropoff. A pickup always
 *    books a window (the chosen one, else the earliest SPX offers) and is
 *    never turned into a drop-off. A caller that sends no method keeps the
 *    older rule: a pickup slot means pickup, none means drop-off.
 */
class SpxFulfillment implements CourierFulfillmentInterface, ProvidesPublicTrackingUrl, ReportsShippingCost
{
    /**
     * Starting pouch sizes, editable in the spx_pouch_presets setting. They
     * are this store's own packaging, not SPX limits: SPX's fee check (2.1)
     * decides what it accepts.
     */
    public const DEFAULT_POUCH_PRESETS = [
        ['label' => 'Small',  'length_cm' => 25, 'width_cm' => 20, 'height_cm' => 10, 'max_weight_kg' => 3],
        ['label' => 'Medium', 'length_cm' => 35, 'width_cm' => 25, 'height_cm' => 15, 'max_weight_kg' => 5],
        ['label' => 'Large',  'length_cm' => 45, 'width_cm' => 35, 'height_cm' => 20, 'max_weight_kg' => 10],
    ];

    /**
     * SPX's parcel_item_type list has no "unspecified" value and orders carry
     * no shipment category, so every booking declares the generic one.
     */
    private const PARCEL_ITEM_TYPE = 'General merchandise';

    /** SPX's published PH limits: weight under 50 kg, each side at most 150 cm, COD in whole pesos up to 50,000. */
    private const MAX_WEIGHT_KG = 50.0;

    private const MAX_SIDE_CM = 150.0;

    private const MAX_COD = 50000;

    /** SPX's documented order status codes. Keys match mappableStatuses(). */
    public const STATUS_LABELS = [
        '1001' => 'Pending Pickup',
        '2001' => 'In Transit',
        '2006' => 'Delivering',
        '3001' => 'On Hold',
        '4001' => 'Delivered',
        '5001' => 'Pickup Failed',
        '5002' => 'Damaged',
        '5003' => 'Lost',
        '6001' => 'Returning',
        '6002' => 'Return Failed',
        '6003' => 'Returned',
        '7001' => 'Cancelled',
    ];

    /**
     * SPX's business-rule codes, grouped, so a code nobody has seen yet still
     * lands somewhere sensible. These four groups are refusals of the
     * shipment: the booking modal's card goes inert.
     *
     * `cod` is an address SPX delivers to, just without collecting cash, and
     * `postcode` is a field the merchant can fix; neither reads as "does not
     * deliver here".
     */
    private const REFUSAL_GROUPS = [
        ServiceabilityVerdict::KIND_DESTINATION => [10016, 10019, 10023, 10026, 10033, 10055, 10062, 10066, 10067, 10068, 10069, 10071, 10075],
        // A pickup address SPX will not collect from blocks every order.
        ServiceabilityVerdict::KIND_ORIGIN      => [10014, 10015, 10018, 10025, 10054, 10065, 10070, 10074],
        // 10017 is COD delivery: the address is served, the cash is not.
        ServiceabilityVerdict::KIND_COD         => [10017, 10029, 10030, 10031, 10032, 10043],
        ServiceabilityVerdict::KIND_POSTCODE    => [10027, 10028],
    ];

    /**
     * Codes that say nothing about whether SPX serves the address, so the
     * card stays clickable: the parcel (not chosen yet when coverage is
     * asked), the pickup window (our own form's field) and SPX's own outages.
     */
    private const UNCERTAIN_GROUPS = [
        ServiceabilityVerdict::KIND_PARCEL     => [10001, 10003, 10004, 10011, 10012, 10050, 10064, 10082, 10083, 10084, 10085, 10086],
        ServiceabilityVerdict::KIND_SLOT       => [10013, 10024, 10038, 10039, 10040, 10056, 10080, 10081],
        ServiceabilityVerdict::KIND_THEIR_SIDE => [10020, 10044, 10045, 10049, 10052, 10057, 10060, 10061, 10076],
    ];

    /**
     * The stable `code` a verdict carries for API clients, the same names
     * the location-list check gives. 10021/10022 (too far, too near) and
     * 10077/10078 (SPX could not place the address on its map) stay unknown:
     * SPX measures from its own geocoding of our text.
     */
    private const VERDICT_CODES = [
        'spx_no_delivery'             => [10016, 10019, 10026, 10033, 10055, 10066, 10067, 10068, 10071, 10075],
        'spx_no_route'                => [10023, 10062, 10069],
        'spx_no_pickup'               => [10014, 10018, 10025, 10054, 10065, 10070, 10074],
        'spx_no_cod'                  => [10017, 10030, 10032],
        'spx_no_cod_pickup'           => [10015, 10029, 10031],
        'spx_invalid_pickup_postcode' => [10027],
        'spx_invalid_postcode'        => [10028],
        'spx_too_far'                 => [10021],
        'spx_too_near'                => [10022],
        'spx_unmapped_pickup'         => [10077],
        'spx_unmapped_address'        => [10078],
    ];

    private const DELIVERED = ['4001'];
    private const CANCELLED = ['7001'];
    /** Coming back to the store: in progress (6001) or completed (6003). */
    private const RETURNING_STATUSES = ['6001', '6003'];
    /** 5001 Pickup Failed happens before SPX has the parcel; 5002 Damaged, 5003 Lost and 6002 Return Failed after. */
    private const FAILED_STATUSES = ['5001', '5002', '5003', '6002'];
    private const FAILED_PRE_PICKUP = ['5001'];
    /**
     * Courier has the parcel. 3001 On Hold is held during delivery: SPX
     * still has it and nothing has failed for good, so it is not a failure
     * and not pre-pickup; its own label ("On Hold") is what the row shows.
     */
    private const IN_TRANSIT_STATUSES = ['2001', '2006', '3001'];

    /** Statuses at which SPX's actual fee (2.2) is final enough to ask for. */
    private const ACTUAL_FEE_STATUSES = ['4001', '6003'];

    /** How long one parcel waits before 2.2 is asked again. */
    private const ACTUAL_FEE_RETRY_SECONDS = 86400;

    /** 1.5 task_status values that settle a batch: 4 Done, 5 Failed. */
    private const CREATE_RESULT_SETTLED = [4, 5];

    /** How long an unconfirmed booking waits for SPX's answer to be found before it is reported as failed. */
    public const UNKNOWN_OUTCOME_MINUTES = 30;

    /** SPX's status for an order it has created and not yet collected; the only one an order can be edited in. */
    public const PENDING_PICKUP = '1001';

    private const NO_PICKUP_TIME = 'SPX has no pickup time available right now.';

    private const CANCEL_TOO_LATE_MESSAGE = 'This shipment has already been picked up (or it is too late to cancel). SPX only allows cancelling orders still pending pickup or drop-off.';

    public function __construct(
        private ?SpxClient $client = null,
        private ?\Closure $sleeper = null,
        private ?SpxLocations $locations = null,
    ) {
        $this->client = $client ?? new SpxClient();
        // Real backoff by default; tests inject a no-op so bounded polling never sleeps.
        $this->sleeper = $sleeper ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    public function getKey(): string
    {
        return 'spx';
    }

    public function getName(): string
    {
        return 'SPX';
    }

    /** Switched on and holding the app-level pair (app id and secret). */
    public function isEnabled(): bool
    {
        $on = Setting::where('group', 'courier')->where('key', 'spx_enabled')->value('value');

        return (bool) $on && $this->client->isConfigured();
    }

    public function configurationIssues(): array
    {
        return $this->client->missingCredentialLabels();
    }

    /** SPX documents one service_type (1: standard). */
    public function getServiceTypes(): array
    {
        return [
            ['value' => 'standard', 'label' => 'Standard Service'],
        ];
    }

    /** The configured pouch presets plus custom dimensions. */
    public function getParcelOptions(): array
    {
        $options = [];
        foreach ($this->pouchPresets() as $preset) {
            $label = (string) ($preset['label'] ?? 'Preset');
            $options[] = [
                'value' => Str::slug($label),
                'label' => sprintf(
                    '%s (%s×%s×%scm, up to %skg)',
                    $label,
                    $preset['length_cm'] ?? '?',
                    $preset['width_cm'] ?? '?',
                    $preset['height_cm'] ?? '?',
                    $preset['max_weight_kg'] ?? '?'
                ),
            ];
        }
        $options[] = ['value' => 'custom', 'label' => 'Custom dimensions…'];

        return $options;
    }

    /** SPX's payment_role: 1 sender pays, 2 receiver pays. */
    public function getShippingPaymentOptions(): array
    {
        return [
            ['value' => 'sender_pay',   'label' => "I'll shoulder it: Sender pays the shipping fee"],
            ['value' => 'receiver_pay', 'label' => 'Customer pays the shipping fee (Receiver pay)'],
        ];
    }

    // --- Booking ---------------------------------------------------------------

    /**
     * 1.4 Create Order V2, then 1.5 Get Order Create Result V2, polled for up
     * to about 15 seconds. Settled: the tracking number. Still running: a
     * `creating` booking that courier:poll-status finishes. Unknown (no
     * answer, or an SPX server error): a `creating` booking marked
     * outcome_unknown with a notice, so the order cannot be booked twice.
     */
    public function createShipment(Order $order, array $opts): array
    {
        if (!$this->client->hasAccountCredentials()) {
            return ['success' => false, 'error' => 'SPX booking requires the User ID / User Secret to be configured (Settings > SPX) before booking.'];
        }

        $priorMeta = self::metaOf($order);
        $opts['reference'] = $this->referenceFor($order, $priorMeta);

        $pickup = self::wantsPickup($opts);
        $changes = [];
        $slot = null;
        if ($pickup) {
            // A pickup always has a window: the one asked for, else the
            // earliest SPX offers. A day already over is replaced the same way.
            $slot = self::decodeSlot($opts['pickup_slot'] ?? null);
            if ($slot === null || self::slotPassed($slot)) {
                $next = $this->nextSlot(null);
                if (!$next['ok']) {
                    return ['success' => false, 'error' => $next['error']];
                }
                $changes[] = self::slotChange($next['slot'], $slot === null ? 'earliest' : 'passed');
                $slot = $next['slot'];
            }
            $opts['pickup_slot'] = self::encodeSlot($slot);
            $opts['method'] = 'pickup';
        } else {
            unset($opts['pickup_slot']);
            $opts['method'] = 'dropoff';
        }

        $built = $this->buildOrderPayload($order, $opts);
        if (!$built['ok']) {
            return ['success' => false, 'error' => $built['error']];
        }
        if (($problem = $this->limitProblem($order, $opts)) !== null) {
            return ['success' => false, 'error' => $problem];
        }

        $context = $this->parcelContext($order, $opts);

        // SPX's own rules, asked without creating anything.
        $check = $this->checkPayload($built['payload'], $context, ServiceabilityService::TIMEOUT_SECONDS * 2);
        if ($check['status'] === 'refused') {
            return ['success' => false, 'error' => $check['refusal']->message(), 'courier_error' => $check['raw']];
        }

        $attempt = $this->submitOrder($built['payload'], $context);

        if ($pickup && $attempt['kind'] === 'refused' && $attempt['code'] === 10080) {
            // Past SPX's cutoff: ask for the windows again, once, and book
            // the one SPX lists after the refused one.
            $refused = $slot;
            $next = $this->nextSlot($refused);
            if (!$next['ok']) {
                return ['success' => false, 'error' => 'The pickup time ' . self::slotLabel($refused) . " is past SPX's cutoff"
                    . ($next['error'] === self::NO_PICKUP_TIME ? ', and SPX has no later pickup time.' : '. ' . $next['error'])];
            }
            $slot = $next['slot'];
            $changes[] = self::slotChange($slot, 'cutoff');
            $opts['pickup_slot'] = self::encodeSlot($slot);
            $built = $this->buildOrderPayload($order, $opts);
            if (!$built['ok']) {
                return ['success' => false, 'error' => $built['error']];
            }
            $attempt = $this->submitOrder($built['payload'], $context);
            if ($attempt['kind'] === 'refused' && $attempt['code'] === 10080) {
                return [
                    'success' => false,
                    'error' => 'SPX refused the pickup times ' . self::slotLabel($refused) . ' and ' . self::slotLabel($slot) . ' as past its cutoff. Choose another pickup time.',
                    'courier_error' => $attempt['raw'] ?? null,
                ];
            }
        }

        $meta = $this->bookingMeta($order, $opts, $built['payload']);
        if ($changes !== []) {
            $before = is_array($priorMeta['pickup_changes'] ?? null) ? array_values($priorMeta['pickup_changes']) : [];
            $meta['pickup_changes'] = array_slice([...$before, ...$changes], -20);
        }

        if ($attempt['kind'] === 'refused' && $attempt['code'] === 13103) {
            return $this->alreadyCreated($order, $priorMeta, $meta, $attempt['batch_no'] ?? null);
        }
        if ($attempt['kind'] === 'unknown') {
            return $this->unconfirmed($meta, $attempt['error']);
        }
        if ($attempt['kind'] === 'refused') {
            return ['success' => false, 'error' => $attempt['error'], 'courier_error' => $attempt['raw'] ?? null];
        }

        $meta['batch_no'] = $attempt['batch_no'];

        if ($attempt['kind'] === 'booked') {
            return [
                'success'         => true,
                'tracking_number' => (string) $attempt['order']['tracking_no'],
                // Not in the create result: the next tracking poll fills it in.
                'status'          => null,
                'meta'            => self::bookedMeta($attempt['order']) + $meta,
            ];
        }

        $meta['pending'] = true;

        return [
            'success'         => true,
            'tracking_number' => null,
            'status'          => 'creating',
            'meta'            => $meta,
        ];
    }

    /**
     * The SPX order_id for this booking: a fresh one, except after an
     * attempt whose outcome was never known, which is retried under the same
     * id so SPX can say it already has it (13103) instead of creating twice.
     */
    private function referenceFor(Order $order, array $priorMeta): string
    {
        $prior = $priorMeta['reference_id'] ?? null;
        if (($priorMeta['outcome_unknown'] ?? false) === true
            && (string) $order->courier_provider === $this->getKey()
            && trim((string) $order->courier_tracking_number) === ''
            && is_string($prior) && preg_match('/^[A-Za-z0-9]{6,32}$/', $prior) === 1) {
            return $prior;
        }

        return self::newReference();
    }

    /** "20261113K7Q2ZD": the booking date (store time zone) and six random characters. */
    public static function newReference(): string
    {
        return now(config('app.timezone'))->format('Ymd') . strtoupper(Str::random(6));
    }

    /**
     * What a booking keeps about itself, whichever way it ends: enough to
     * look it up again, to print, and to edit it with the values it was
     * booked with.
     *
     * @return array<string, mixed>
     */
    private function bookingMeta(Order $order, array $opts, array $payload): array
    {
        $parcel = $payload['parcel_info'];
        $fulfillment = $payload['fulfillment_info'];

        return array_filter([
            'mode'             => Setting::where('group', 'courier')->where('key', 'spx_mode')->value('value') ?: 'uat',
            'service'          => $opts['service'] ?? 'standard',
            'parcel'           => $opts['parcel'] ?? null,
            'shipping_payment' => $opts['shipping_payment'] ?? 'sender_pay',
            'reference_id'     => $payload['order_id'],
            'method'           => $opts['method'] ?? 'dropoff',
            'pickup_slot'      => ($opts['method'] ?? null) === 'pickup' ? ($opts['pickup_slot'] ?? null) : null,
            'cod'              => (bool) $fulfillment['cod_collection'],
            'cod_amount'       => $fulfillment['cod_amount'] ?? null,
            'insure'           => (float) $parcel['express_insured_value'] > 0,
            'insured_value'    => $parcel['express_insured_value'],
            'weight_kg'        => $parcel['parcel_weight'],
            'length_cm'        => $parcel['parcel_length'],
            'width_cm'         => $parcel['parcel_width'],
            'height_cm'        => $parcel['parcel_height'],
        ], fn ($v) => $v !== null);
    }

    /**
     * A booking SPX may or may not have: kept as `creating` so the order
     * cannot be booked again, with its order_id, and checked by
     * courier:poll-status (resolvePendingCreation()).
     */
    private function unconfirmed(array $meta, string $why): array
    {
        $meta['pending'] = true;
        $meta['outcome_unknown'] = true;
        $meta['unknown_since'] = now()->toIso8601String();
        unset($meta['batch_no']);

        Log::warning('SPX booking outcome unknown', ['reference' => $meta['reference_id'] ?? null, 'why' => mb_substr($why, 0, 300)]);

        return [
            'success'         => true,
            'tracking_number' => null,
            'status'          => 'creating',
            'meta'            => $meta,
            'notice'          => 'SPX did not confirm this booking (' . ($meta['reference_id'] ?? '') . '). It stays in Booking while SPX is checked; it is not sent again, so SPX cannot create it twice.',
        ];
    }

    /**
     * One 1.4 submit and its bounded 1.5 poll.
     *
     * @return array{kind:'booked', order:array, batch_no:int|string}
     *       | array{kind:'pending', batch_no:int|string}
     *       | array{kind:'refused', error:string, raw:?string, code:?int, batch_no?:int|string}
     *       | array{kind:'unknown', error:string}
     */
    private function submitOrder(array $payload, array $context): array
    {
        try {
            $createData = $this->client->createOrders([$payload]);
        } catch (SpxApiException $e) {
            if ($e->isServerError()) {
                // A server error: SPX may have taken the order anyway.
                return ['kind' => 'unknown', 'error' => "SPX answered with a server error (ret_code {$e->retCode})."];
            }

            return [
                'kind'  => 'refused',
                'error' => SpxRefusals::explain($e->retCode, $e->getMessage(), $context)->message(),
                'raw'   => self::rawText($e->retCode, $e->getMessage(), null),
                'code'  => $e->retCode,
            ];
        } catch (\Throwable $e) {
            // No answer, a timeout or a non-envelope page: the order may exist at SPX.
            return ['kind' => 'unknown', 'error' => 'SPX did not answer the booking request.'];
        }

        $batchNo = $createData['batch_no'] ?? null;
        if (!$batchNo) {
            // SPX accepted the request but gave nothing to follow it by.
            return ['kind' => 'unknown', 'error' => 'SPX did not return a batch number for this booking.'];
        }

        $resultData = $this->pollCreateResult((int) $batchNo);
        if ($resultData === null) {
            return ['kind' => 'pending', 'batch_no' => $batchNo];
        }

        $outcome = self::outcomeFromCreateResult($resultData, $context);

        return $outcome['ok']
            ? ['kind' => 'booked', 'order' => $outcome['order'], 'batch_no' => $batchNo]
            : ['kind' => 'refused', 'error' => $outcome['error'], 'raw' => $outcome['raw'] ?? null, 'code' => $outcome['code'] ?? null, 'batch_no' => $batchNo];
    }

    /**
     * SPX answered 13103: it already has an order with this order_id, from an
     * earlier attempt whose answer never arrived. The order is looked up and,
     * when found, booked with it. When it cannot be found the booking stays
     * unconfirmed: a parcel exists at SPX that must not be forgotten.
     */
    private function alreadyCreated(Order $order, array $priorMeta, array $meta, int|string|null $refusedBatch): array
    {
        $ref = (string) $meta['reference_id'];
        $found = $this->existingOrder($order, $ref, $priorMeta, $refusedBatch === null ? null : (int) $refusedBatch);
        if ($found === null) {
            return $this->unconfirmed($meta, 'SPX already has this order id but it could not be found.');
        }

        return [
            'success'         => true,
            'tracking_number' => (string) $found['order']['tracking_no'],
            'status'          => $found['status'],
            'meta'            => self::bookedMeta($found['order']) + ['batch_no' => $found['batch_no'], 'recovered' => 13103] + $meta,
            'notice'          => "SPX already had order {$ref} from an earlier attempt, so that booking was kept.",
        ];
    }

    /**
     * The SPX order this booking already has: in the earlier batch (1.5) when
     * one is on file, else by Track (1.2) on the tracking number on file or
     * the order_id itself. A Track answer counts only when it names this
     * order_id and does not name another receiver's phone. Best effort.
     *
     * @return array{order:array<string, mixed>, batch_no:int|string|null, status:?string}|null
     */
    private function existingOrder(Order $order, string $ref, array $priorMeta, ?int $refusedBatch): ?array
    {
        $prior = $priorMeta['batch_no'] ?? null;
        if (is_numeric($prior) && (int) $prior !== $refusedBatch) {
            try {
                $result = $this->client->getOrderCreateResult((int) $prior);
                foreach (($result['orders'] ?? []) as $o) {
                    if (is_array($o) && (string) ($o['order_id'] ?? '') === $ref && !empty($o['tracking_no'])) {
                        return ['order' => $o, 'batch_no' => $prior, 'status' => null];
                    }
                }
            } catch (\Throwable) {
                // falls through to Track
            }
        }

        $known = trim((string) $order->courier_tracking_number);
        foreach (array_values(array_unique(array_filter([$known, $ref]))) as $asked) {
            try {
                $data = $this->client->trackOrders([$asked]);
            } catch (\Throwable) {
                continue;
            }
            foreach (($data['orders'] ?? []) as $o) {
                $tracking = is_array($o) && is_string($o['tracking_no'] ?? null) ? trim($o['tracking_no']) : '';
                if ($tracking === '' || !self::sameRecipientPhone($order, $o)) {
                    continue;
                }
                $orderId = (string) ($o['order_id'] ?? '');
                if ($orderId === $ref || ($orderId === '' && $asked === $known && $tracking === $known)) {
                    $status = $o['status_code'] ?? null;

                    return [
                        // Track also answers names, phones and addresses: only what identifies the order is kept.
                        'order'    => array_intersect_key($o, array_flip(['order_id', 'tracking_no', 'tracking_link', 'status_code'])),
                        'batch_no' => null,
                        'status'   => is_scalar($status) && (string) $status !== '' ? (string) $status : null,
                    ];
                }
            }
        }

        return null;
    }

    /** False only when Track names a receiver phone that is not this order's (a missing phone proves nothing). */
    private static function sameRecipientPhone(Order $order, array $tracked): bool
    {
        $said = $tracked['deliver_info']['deliver_phone'] ?? null;
        $theirs = PhPhone::normalize(is_scalar($said) ? (string) $said : null);
        $ours = PhPhone::normalize($order->shipping_phone ?: $order->phone);
        if ($theirs === null || $ours === null) {
            return true;
        }

        return substr($theirs, -10) === substr($ours, -10);
    }

    /** @return array{at:string, slot:string, label:string, reason:string} one pickup-time change, kept with the booking */
    private static function slotChange(array $slot, string $reason): array
    {
        return ['at' => now()->toIso8601String(), 'slot' => self::encodeSlot($slot), 'label' => self::slotLabel($slot), 'reason' => $reason];
    }

    /** Collected or dropped off: the explicit `method`, else whether a pickup slot came. */
    private static function wantsPickup(array $opts): bool
    {
        return match ($opts['method'] ?? null) {
            'pickup' => true,
            'dropoff' => false,
            default => self::decodeSlot($opts['pickup_slot'] ?? null) !== null,
        };
    }

    /** @return array<string, mixed> */
    private static function metaOf(Order $order): array
    {
        $meta = $order->courier_meta ? json_decode((string) $order->courier_meta, true) : null;

        return is_array($meta) ? $meta : [];
    }

    // --- Cancel and tracking ------------------------------------------------------

    /**
     * 1.3 Cancel Order. 13002 (too late to cancel) arrives either as the
     * whole call's ret_code or inside fail_list; both get the same sentence.
     */
    public function cancelShipment(string $trackingNumber): array
    {
        if (!$this->client->hasAccountCredentials()) {
            return ['success' => false, 'error' => 'SPX User ID / User Secret not configured.'];
        }

        try {
            $data = $this->client->cancelOrders([$trackingNumber]);
        } catch (SpxApiException $e) {
            if ($e->retCode === 13002) {
                return ['success' => false, 'error' => self::CANCEL_TOO_LATE_MESSAGE];
            }

            return ['success' => false, 'error' => "SPX cancel failed: {$e->getMessage()} (ret_code {$e->retCode})"];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'SPX cancel failed: SPX did not answer.'];
        }

        if (in_array($trackingNumber, $data['tracking_no_list'] ?? [], true)) {
            return ['success' => true];
        }

        foreach (($data['fail_list'] ?? []) as $fail) {
            if (($fail['tracking_no'] ?? null) === $trackingNumber) {
                if ((int) ($fail['ret_code'] ?? 0) === 13002) {
                    return ['success' => false, 'error' => self::CANCEL_TOO_LATE_MESSAGE];
                }

                return ['success' => false, 'error' => $fail['message'] ?? 'SPX cancel failed.'];
            }
        }

        return ['success' => false, 'error' => 'SPX did not confirm the cancellation.'];
    }

    /**
     * 1.2 Track Order. Retries twice on 13251 (not yet propagated, which SPX
     * answers right after a create), whether it comes as the call's ret_code
     * or inside fail_list. Once the parcel is delivered or returned, also
     * asks 2.2 for the actual fee, at most once per parcel per day.
     */
    public function getTracking(string $trackingNumber): array
    {
        if (!$this->client->hasAccountCredentials()) {
            return ['success' => false, 'error' => 'SPX User ID / User Secret not configured.'];
        }

        $data = null;
        $lastError = null;
        $backoffMs = [1500, 3000];
        $attempts = count($backoffMs) + 1;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $data = $this->client->trackOrders([$trackingNumber]);
                $lastError = null;
            } catch (SpxApiException $e) {
                $lastError = $e;
                $data = null;
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'SPX tracking failed: SPX did not answer.'];
            }

            $notYetPropagated = $lastError !== null && $lastError->retCode === 13251;
            if (!$notYetPropagated && is_array($data)) {
                foreach (($data['fail_list'] ?? []) as $fail) {
                    if (($fail['tracking_no'] ?? null) === $trackingNumber && (int) ($fail['ret_code'] ?? 0) === 13251) {
                        $notYetPropagated = true;
                        break;
                    }
                }
            }

            if (!$notYetPropagated) {
                break;
            }
            if ($i < $attempts - 1) {
                ($this->sleeper)($backoffMs[$i]);
            }
        }

        if ($lastError !== null) {
            return ['success' => false, 'error' => "SPX tracking failed: {$lastError->getMessage()} (ret_code {$lastError->retCode})"];
        }

        $found = null;
        foreach (($data['orders'] ?? []) as $o) {
            if (($o['tracking_no'] ?? null) === $trackingNumber) {
                $found = $o;
                break;
            }
        }

        if (!$found) {
            foreach (($data['fail_list'] ?? []) as $fail) {
                if (($fail['tracking_no'] ?? null) === $trackingNumber) {
                    return ['success' => false, 'error' => $fail['message'] ?? 'SPX tracking failed.'];
                }
            }

            return ['success' => false, 'error' => 'SPX returned no data for this tracking number.'];
        }

        $status = $found['status_code'] ?? $found['status'] ?? null;
        $status = is_scalar($status) ? (string) $status : null;
        $feeInfo = $this->actualFeeInfo($trackingNumber, $status);

        return [
            'success'    => true,
            'status'     => $status,
            'raw'        => $found,
            'links'      => self::deliveryLink($found['tracking_link'] ?? null),
            'timeline'   => self::timelineFromRoutes($found['routes'] ?? null),
            'fee_info'   => $feeInfo,
            'actual_fee' => is_array($feeInfo) ? self::amount($feeInfo['actual_shipping_fee'] ?? null) : null,
            'meta'       => array_filter([
                'edd'               => self::edd($found['edd_info']['edd_min'] ?? null, $found['edd_info']['edd_max'] ?? null, 'tracking'),
                'actual_return_fee' => is_array($feeInfo) ? self::positiveAmount($feeInfo['return_shipping_fee'] ?? null) : null,
            ], fn ($v) => $v !== null),
        ];
    }

    /**
     * 2.2 Get Order Fee for one parcel, or null. Asked only once the parcel
     * is delivered or returned, and at most once per parcel per day: the
     * webhook carries the running fee before that, and asking on every poll
     * spent a call per parcel per run. Best effort.
     *
     * @return array<string, mixed>|null SPX's fee_info
     */
    private function actualFeeInfo(string $trackingNumber, ?string $status): ?array
    {
        if (!in_array($status, self::ACTUAL_FEE_STATUSES, true)) {
            return null;
        }
        if (!Cache::add('courier-fulfillment:spx:actual-fee:' . $trackingNumber, now()->getTimestamp(), self::ACTUAL_FEE_RETRY_SECONDS)) {
            return null;
        }

        try {
            $feeData = $this->client->getActualFee([$trackingNumber]);
        } catch (\Throwable) {
            return null;
        }

        foreach (($feeData['orders'] ?? []) as $fo) {
            if (is_array($fo) && ($fo['tracking_no'] ?? null) === $trackingNumber) {
                return is_array($fo['fee_info'] ?? null) ? $fo['fee_info'] : null;
            }
        }

        return null;
    }

    /** SPX's routes[] history as the shared {status, at} timeline. */
    public static function timelineFromRoutes(?array $routes): array
    {
        if (empty($routes)) {
            return [];
        }
        $events = [];
        foreach ($routes as $r) {
            $at = $r['timestamp'] ?? null;
            if (!is_int($at)) {
                continue;
            }
            $events[] = ['status' => (string) ($r['status_code'] ?? $r['status'] ?? ''), 'at' => $at];
        }
        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        return array_values($events);
    }

    /** Final states. 5001 and 6002 are not final: SPX retries them. 3001 On Hold is not final either. */
    public function terminalStatuses(): array
    {
        return [...self::DELIVERED, '5002', '5003', '6003', ...self::CANCELLED];
    }

    /** Only orders still pending pickup or drop-off can be cancelled. */
    public function cancelableStatuses(): array
    {
        return [self::PENDING_PICKUP];
    }

    /**
     * Every SPX status code plus this courier's two bridging statuses
     * (`creating`: create in flight or unconfirmed; `creation_failed`) on the
     * shared spine.
     */
    public function statusPhase(?string $status): array
    {
        $s = (string) $status;

        if ($s === 'creating') {
            return ['phase' => 'booking', 'step' => 1, 'failed' => false];
        }
        if ($s === 'creation_failed') {
            return ['phase' => 'failed', 'step' => 1, 'failed' => true];
        }
        if (in_array($s, self::DELIVERED, true)) {
            return ['phase' => 'delivered', 'step' => 3, 'failed' => false];
        }
        // The literal canceled/cancelled the admin Cancel action writes buckets with SPX's own 7001.
        if (in_array($s, self::CANCELLED, true) || in_array($s, FulfillmentService::CANCELED_STATUSES, true)) {
            return ['phase' => 'cancelled', 'step' => 1, 'failed' => true];
        }
        if (in_array($s, self::RETURNING_STATUSES, true)) {
            return ['phase' => 'returning', 'step' => 2, 'failed' => true];
        }
        if (in_array($s, self::FAILED_STATUSES, true)) {
            $prePickup = in_array($s, self::FAILED_PRE_PICKUP, true);

            return ['phase' => 'failed', 'step' => $prePickup ? 1 : 2, 'failed' => true];
        }
        if (in_array($s, self::IN_TRANSIT_STATUSES, true)) {
            return ['phase' => 'in_transit', 'step' => 2, 'failed' => false];
        }

        // 1001 Pending Pickup, and anything unrecognized: booked, awaiting collection or drop-off.
        return ['phase' => 'to_handover', 'step' => 1, 'failed' => false];
    }

    // --- Fees and coverage --------------------------------------------------------

    /**
     * 2.1 Check Shipping Fee, the estimate shown before booking. Null when
     * the order or the pickup address is not complete enough to ask, or SPX
     * gives no fee. remote_area_fee and odz (out of SPX's normal delivery
     * zone) are information only: an address with a surcharge is still served.
     */
    public function estimateFee(Order $order, array $options): ?object
    {
        if (!$this->client->hasAccountCredentials()) {
            return null;
        }

        unset($options['pickup_slot']);
        $built = $this->buildOrderPayload($order, $options);
        if (!$built['ok']) {
            return null;
        }

        try {
            $data = $this->client->checkOrderFee([$built['payload']]);
        } catch (\Throwable) {
            return null;
        }

        $first = ($data['orders'] ?? [])[0] ?? null;
        if (!is_array($first) || !isset($first['estimated_shipping_fee']) || !is_numeric($first['estimated_shipping_fee'])) {
            return null;
        }

        $amount = (float) $first['estimated_shipping_fee'];

        return (object) [
            'amount'          => $amount,
            'currency'        => 'PHP',
            'label'           => '₱' . number_format($amount, 2),
            'remote_area_fee' => self::amount($first['remote_area_fee'] ?? null),
            'odz'             => self::odz($first),
        ];
    }

    /**
     * The booking's final check, also usable from the estimate button: our
     * own checks first, then 2.1 with the exact booking body. SPX applies the
     * same parcel rules there as at create, so a parcel SPX would refuse is
     * refused here, in plain words, before anything is created.
     *
     * The pickup window is left out: the fee check needs none, and a window
     * past SPX's cutoff is moved by createShipment() itself.
     *
     * @return array{status:'fee', amount:float, remote_area_fee:?float, odz:?bool}
     *       | array{status:'refused', refusal:CourierRefusal, raw:?string}
     *       | array{status:'unavailable', raw:?string}
     */
    public function quoteFee(Order $order, array $options): array
    {
        if (!$this->client->hasAccountCredentials()) {
            return ['status' => 'unavailable', 'raw' => null];
        }

        unset($options['pickup_slot']);
        $built = $this->buildOrderPayload($order, $options);
        if (!$built['ok']) {
            return ['status' => 'refused', 'refusal' => new CourierRefusal("SPX can't book this shipment", $built['error']), 'raw' => null];
        }
        if (($problem = $this->limitProblem($order, $options)) !== null) {
            return ['status' => 'refused', 'refusal' => new CourierRefusal("SPX can't book this shipment", $problem), 'raw' => null];
        }

        return $this->checkPayload($built['payload'], $this->parcelContext($order, $options), ServiceabilityService::TIMEOUT_SECONDS * 2);
    }

    /** 2.1 with a built body; see quoteFee(). */
    private function checkPayload(array $payload, array $context, int $timeout): array
    {
        try {
            $data = $this->client->checkOrderFee([$payload], $timeout);
        } catch (SpxApiException $e) {
            $raw = self::rawText($e->retCode, $e->getMessage(), null);

            return !$e->isServerError() && SpxRefusals::isRefusal($e->retCode)
                ? ['status' => 'refused', 'refusal' => SpxRefusals::explain($e->retCode, $e->getMessage(), $context), 'raw' => $raw]
                : ['status' => 'unavailable', 'raw' => $raw];
        } catch (\Throwable $e) {
            Log::warning('SPX fee check could not reach SPX', ['exception' => $e::class]);

            return ['status' => 'unavailable', 'raw' => null];
        }

        $first = ($data['orders'] ?? [])[0] ?? null;
        if (is_array($first) && isset($first['estimated_shipping_fee']) && is_numeric($first['estimated_shipping_fee'])) {
            return [
                'status'          => 'fee',
                'amount'          => (float) $first['estimated_shipping_fee'],
                'remote_area_fee' => self::amount($first['remote_area_fee'] ?? null),
                'odz'             => self::odz($first),
            ];
        }

        // A per-order failure comes back in data.fail_list with its own ret_code.
        $failed = ($data['fail_list'] ?? [])[0] ?? null;
        if (is_array($failed)) {
            $code = is_numeric($failed['ret_code'] ?? null) ? (int) $failed['ret_code'] : null;
            $failure = self::createFailure($failed, $context);
            $text = trim(((string) ($failed['message'] ?? '')) . ' ' . ((string) ($failed['debug_msg'] ?? '')));

            return SpxRefusals::isRefusal($code)
                ? ['status' => 'refused', 'refusal' => SpxRefusals::explain($code, $text, $context), 'raw' => $failure['raw']]
                : ['status' => 'unavailable', 'raw' => $failure['raw']];
        }

        return ['status' => 'unavailable', 'raw' => null];
    }

    /**
     * Out of SPX's normal delivery zone: remote_area_fee above zero. The field
     * is not promised in every answer, so its absence is "not known", never
     * "no surcharge".
     */
    private static function odz(array $order): ?bool
    {
        $fee = self::amount($order['remote_area_fee'] ?? null);

        return $fee === null ? null : $fee > 0;
    }

    /**
     * Will SPX carry this order? Our own location list answers first: an
     * address SPX does not list, or a barangay where it does not deliver or
     * collect COD, is a refusal stated without asking SPX. Otherwise 2.1 is
     * asked with the smallest preset (a from-price). A transport failure or
     * an SPX outage is `unknown`, never a refusal.
     */
    public function checkServiceability(Order $order): ServiceabilityVerdict
    {
        if (!$this->client->hasAccountCredentials()) {
            return ServiceabilityVerdict::unknown(ServiceabilityVerdict::KIND_UNREACHABLE);
        }

        $built = $this->buildOrderPayload($order, []);
        if (!$built['ok'] && isset($built['kind'])) {
            return ServiceabilityVerdict::notServiceable($built['kind'], $built['error'], $built['code'] ?? null);
        }
        if (!$built['ok']) {
            // Our own missing data: nothing was asked and nothing was refused.
            return ServiceabilityVerdict::unknown(ServiceabilityVerdict::KIND_UNREACHABLE, null, $built['code'] ?? null);
        }

        try {
            $data = $this->client->checkOrderFee([$built['payload']], ServiceabilityService::TIMEOUT_SECONDS);
        } catch (SpxApiException $e) {
            if ($e->isServerError()) {
                return ServiceabilityVerdict::unknown(ServiceabilityVerdict::KIND_THEIR_SIDE);
            }

            return self::verdictForCode($e->retCode, $e->getMessage());
        } catch (\Throwable $e) {
            // DNS, connection, timeout or not an SPX envelope: we could not ask.
            Log::warning('SPX serviceability check could not reach SPX', ['order' => $order->id, 'exception' => $e::class]);

            return ServiceabilityVerdict::unknown(ServiceabilityVerdict::KIND_UNREACHABLE);
        }

        $first = ($data['orders'] ?? [])[0] ?? null;
        if (is_array($first) && isset($first['estimated_shipping_fee'])) {
            return ServiceabilityVerdict::serviceable(['estimate' => (float) $first['estimated_shipping_fee']], 'PHP');
        }

        $failed = ($data['fail_list'] ?? [])[0] ?? null;
        if (is_array($failed)) {
            return self::verdictForCode((int) ($failed['ret_code'] ?? 0), (string) ($failed['message'] ?? ''));
        }

        return ServiceabilityVerdict::unknown(ServiceabilityVerdict::KIND_THEIR_SIDE);
    }

    /** Which group a code belongs to, and whether that group is a refusal. A null kind is a code we do not know. */
    private static function classify(int $code): array
    {
        foreach (self::REFUSAL_GROUPS as $kind => $codes) {
            if (in_array($code, $codes, true)) {
                return [$kind, true];
            }
        }
        foreach (self::UNCERTAIN_GROUPS as $kind => $codes) {
            if (in_array($code, $codes, true)) {
                return [$kind, false];
            }
        }

        return [null, false];
    }

    private static function verdictCode(int $code): ?string
    {
        foreach (self::VERDICT_CODES as $name => $codes) {
            if (in_array($code, $codes, true)) {
                return $name;
            }
        }

        return null;
    }

    /** One SPX code as a verdict, carrying SPX's words only when we do not recognise it. */
    private static function verdictForCode(int $code, string $message): ServiceabilityVerdict
    {
        [$kind, $isRefusal] = self::classify($code);
        $said = trim($message) !== '' ? trim($message) : null;

        return $isRefusal
            ? ServiceabilityVerdict::notServiceable($kind, $said, self::verdictCode($code))
            : ServiceabilityVerdict::unknown($kind, $said, self::verdictCode($code));
    }

    // --- Pickup windows -----------------------------------------------------------

    /**
     * 1.1 Get Pickup Timeslot: every window SPX offers, one option per
     * window, in SPX's order (within a day, range id 0 "Now" first). A day
     * already over in the store's time zone, or one with no usable window,
     * is skipped. Each value carries exactly the three fields create needs.
     * An empty list means none could be fetched; a pickup booking then asks
     * SPX again itself.
     */
    public function getPickupSlots(Order $order): array
    {
        try {
            $slots = $this->availableSlots();
        } catch (\Throwable $e) {
            Log::warning('SPX pickup times could not be fetched', ['exception' => $e::class]);

            return [];
        }

        return array_map(fn (array $slot) => [
            'value' => self::encodeSlot($slot),
            'label' => self::slotLabel($slot),
        ], $slots);
    }

    /**
     * @return list<array{pickup_time:int, pickup_time_range_id:int, pickup_time_range:string}>
     * @throws \Throwable when SPX could not be asked
     */
    private function availableSlots(): array
    {
        if (!$this->client->hasAccountCredentials()) {
            return [];
        }

        $days = $this->client->getPickupTime(1);
        $today = now(config('app.timezone'))->format('Y-m-d');

        $options = [];
        foreach (is_array($days) ? $days : [] as $day) {
            $pickupTime = $day['pickup_time'] ?? null;
            $slots = $day['slots'] ?? null;
            if (!is_numeric($pickupTime) || !is_array($slots) || $slots === [] || self::slotDate((int) $pickupTime) < $today) {
                continue;
            }

            $now = [];
            $later = [];
            foreach ($slots as $slot) {
                if (!is_array($slot) || !is_numeric($slot['pickup_time_range_id'] ?? null)) {
                    continue;
                }
                $entry = [
                    'pickup_time'          => (int) $pickupTime,
                    'pickup_time_range_id' => (int) $slot['pickup_time_range_id'],
                    'pickup_time_range'    => (string) ($slot['pickup_time_range'] ?? ''),
                ];
                if ($entry['pickup_time_range_id'] === 0) {
                    $now[] = $entry;
                } else {
                    $later[] = $entry;
                }
            }
            array_push($options, ...$now, ...$later);
        }

        return $options;
    }

    /**
     * The window to book: the earliest SPX offers, or after $refused (a window
     * SPX just turned down) the one listed right after it.
     */
    private function nextSlot(?array $refused): array
    {
        try {
            $slots = $this->availableSlots();
        } catch (\Throwable $e) {
            Log::warning('SPX pickup times could not be fetched for a booking', ['exception' => $e::class]);

            return ['ok' => false, 'error' => "Couldn't get SPX's pickup times. Try again, or choose drop-off."];
        }

        if ($refused !== null) {
            foreach ($slots as $i => $slot) {
                if ($slot['pickup_time'] === $refused['pickup_time'] && $slot['pickup_time_range_id'] === $refused['pickup_time_range_id']) {
                    $slots = array_slice($slots, $i + 1);
                    break;
                }
            }
        }

        return $slots === []
            ? ['ok' => false, 'error' => self::NO_PICKUP_TIME]
            : ['ok' => true, 'slot' => $slots[0]];
    }

    private static function encodeSlot(array $slot): string
    {
        return (string) json_encode([
            'pickup_time'          => (int) $slot['pickup_time'],
            'pickup_time_range_id' => (int) $slot['pickup_time_range_id'],
            'pickup_time_range'    => (string) $slot['pickup_time_range'],
        ]);
    }

    /** "Tue, Nov 10 · 2:00pm-4:00pm": the day (store time zone) and SPX's own window text. */
    public static function slotLabel(array $slot): string
    {
        $day = \Carbon\Carbon::createFromTimestamp((int) ($slot['pickup_time'] ?? 0))
            ->timezone(config('app.timezone'))
            ->format('D, M d');
        $range = trim((string) ($slot['pickup_time_range'] ?? ''));

        return $range === '' ? $day : "{$day} · {$range}";
    }

    private static function slotDate(int $pickupTime): string
    {
        return \Carbon\Carbon::createFromTimestamp($pickupTime)->timezone(config('app.timezone'))->format('Y-m-d');
    }

    /** A window whose day is already over. A window later today is SPX's to judge (10080). */
    private static function slotPassed(array $slot): bool
    {
        return self::slotDate((int) $slot['pickup_time']) < now(config('app.timezone'))->format('Y-m-d');
    }

    /** @return array{pickup_time:int, pickup_time_range_id:int, pickup_time_range:string}|null */
    private static function decodeSlot(mixed $raw): ?array
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !is_numeric($decoded['pickup_time'] ?? null) || !is_numeric($decoded['pickup_time_range_id'] ?? null)
            || !is_string($decoded['pickup_time_range'] ?? null)) {
            return null;
        }

        return [
            'pickup_time'          => (int) $decoded['pickup_time'],
            'pickup_time_range_id' => (int) $decoded['pickup_time_range_id'],
            'pickup_time_range'    => $decoded['pickup_time_range'],
        ];
    }

    // --- Labels -------------------------------------------------------------------

    /**
     * 1.6 Get AWB V2. SPX's label link lasts half an hour and opens at most
     * ten times, so the PDF is downloaded once, stored on the private disk
     * and served through this app's own authenticated route.
     *
     * $format is accepted for the interface and ignored: SPX has one label shape.
     */
    public function printLabel(string $trackingNumber, string $format): array
    {
        // The number becomes part of a storage path: only a plain token is looked up or stored.
        if (!self::isPlainTrackingNumber($trackingNumber)) {
            return ['success' => false, 'error' => 'That is not an SPX tracking number.'];
        }

        $order = Order::where('courier_tracking_number', $trackingNumber)
            ->where('courier_provider', $this->getKey())
            ->first();
        if (!$order) {
            return ['success' => false, 'error' => 'No SPX order found for this tracking number.'];
        }

        // A cancelled order's waybill is void, cached or not.
        if (($this->statusPhase($order->courier_status)['phase'] ?? null) === 'cancelled') {
            return ['success' => false, 'error' => 'This SPX booking is cancelled. Cancelled shipments have no printable waybill. Rebook the order to get a fresh label.'];
        }

        $path = self::labelStoragePath($trackingNumber);
        if (!Storage::disk('local')->exists($path)) {
            $stored = $this->fetchAndStoreLabel($order, $path);
            if (!$stored['ok']) {
                return ['success' => false, 'error' => $stored['error']];
            }
        }

        return [
            'success' => true,
            'url'     => route('admin.fulfillment.label-file', ['order' => $order->id]),
        ];
    }

    /** Where a downloaded waybill is kept on the local (private) disk; FulfillmentController::labelFile() reads it. */
    public static function labelStoragePath(string $trackingNumber): string
    {
        return "courier-labels/spx/{$trackingNumber}.pdf";
    }

    /** Letters, digits, dash and underscore only: safe as a file name. */
    public static function isPlainTrackingNumber(string $trackingNumber): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $trackingNumber) === 1;
    }

    /** @return array{ok:bool, error?:string} */
    private function fetchAndStoreLabel(Order $order, string $path): array
    {
        if (!$this->client->hasAccountCredentials()) {
            return ['ok' => false, 'error' => 'SPX User ID / User Secret not configured.'];
        }

        $batchNo = self::metaOf($order)['batch_no'] ?? null;
        if (!$batchNo) {
            return ['ok' => false, 'error' => 'No SPX batch number on file for this order. Cannot fetch a label.'];
        }

        $result = $this->pollLabelResult((int) $batchNo, (string) $order->courier_tracking_number);
        if ($result instanceof SpxApiException) {
            return ['ok' => false, 'error' => "SPX label fetch failed: {$result->getMessage()} (ret_code {$result->retCode})"];
        }
        if ($result === null) {
            return ['ok' => false, 'error' => 'SPX label generation is still in progress, try again shortly.'];
        }

        // SPX's test system answers {awb_link, fail_list} with no task_status
        // at all, so "done" is the link's presence; task_status is honored
        // when present (5 is failed whatever else came).
        $taskStatus = array_key_exists('task_status', $result) ? (int) $result['task_status'] : null;
        $awbLink = $result['awb_link'] ?? null;

        foreach (($result['fail_list'] ?? []) as $fail) {
            if (($fail['tracking_no'] ?? null) === $order->courier_tracking_number) {
                return ['ok' => false, 'error' => $fail['message'] ?? 'SPX could not generate a label for this shipment.'];
            }
        }
        if ($taskStatus === 5) {
            return ['ok' => false, 'error' => 'SPX label generation failed: ' . ($result['description'] ?? 'the batch failed.')];
        }
        if (!is_string($awbLink) || $awbLink === '' || self::url($awbLink) === null || !str_starts_with(strtolower($awbLink), 'https://')) {
            return ['ok' => false, 'error' => 'SPX label generation failed: ' . ($result['description'] ?? 'no label link came back.')];
        }

        try {
            $pdf = Http::timeout(30)->withoutRedirecting()->get($awbLink);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Failed to download the SPX label PDF.'];
        }

        if (!$pdf->successful() || $pdf->body() === '') {
            return ['ok' => false, 'error' => "SPX label download failed (HTTP {$pdf->status()})."];
        }

        // The link can answer HTTP 200 with a JSON error instead of a PDF, so
        // the bytes themselves are checked before anything is stored.
        $body = $pdf->body();
        if (!str_starts_with($body, '%PDF')) {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && (isset($decoded['retcode']) || isset($decoded['message']))) {
                $retcode = $decoded['retcode'] ?? 'unknown';
                $message = mb_substr((string) ($decoded['message'] ?? 'unknown error'), 0, 200);

                return ['ok' => false, 'error' => "SPX label service error: {$message} (retcode {$retcode})"];
            }

            return ['ok' => false, 'error' => 'SPX returned a label that is not a valid PDF.'];
        }

        Storage::disk('local')->put($path, $body);

        return ['ok' => true];
    }

    /**
     * Two-phase label fetch. The documented 1.6 v2 endpoint gets a short
     * window (two attempts): on SPX's test system it can sit at task_status 3
     * indefinitely. Then the v1 sibling (batch_no plus tracking_no_list),
     * which answers synchronously there. An SPX error from v1 wins over one
     * from v2; a transport failure in either is treated as transient.
     */
    private function pollLabelResult(int $batchNo, string $trackingNumber): array|SpxApiException|null
    {
        $v2BackoffMs = [1500];
        $v2Attempts = count($v2BackoffMs) + 1;
        $v2Exception = null;

        for ($i = 0; $i < $v2Attempts; $i++) {
            try {
                $result = $this->client->getShippingLabel($batchNo);
            } catch (SpxApiException $e) {
                $v2Exception = $e;
                break;
            } catch (\Throwable) {
                $result = null;
            }

            if (is_array($result) && self::labelResultSettled($result)) {
                return $result;
            }
            if ($i < $v2Attempts - 1) {
                ($this->sleeper)($v2BackoffMs[$i]);
            }
        }

        try {
            $v1Result = $this->client->getShippingLabelV1($batchNo, [$trackingNumber]);
        } catch (SpxApiException $e) {
            return $e;
        } catch (\Throwable) {
            $v1Result = null;
        }

        if (is_array($v1Result) && self::labelResultSettled($v1Result)) {
            return $v1Result;
        }

        return $v2Exception;
    }

    private static function labelResultSettled(array $result): bool
    {
        if (!empty($result['awb_link']) || !empty($result['fail_list'])) {
            return true;
        }

        return array_key_exists('task_status', $result)
            && in_array((int) $result['task_status'], self::CREATE_RESULT_SETTLED, true);
    }

    // --- Webhooks -----------------------------------------------------------------

    /**
     * 4.1 Tracking webhook: the body is one event. Besides the status an
     * event carries `actual_fee` (actual_shipping_fee: the latest one counts)
     * and `meta`: the tracking link, the delivery estimate, the driver, the
     * proof-of-delivery photos and, on a return, the return fee.
     *
     * A body without status_code is 4.2 Evidence Proofs when it carries proof
     * lists: one event with no status. Anything else yields no events.
     *
     * SPX's order_id is the booking's own reference (`courier_reference`),
     * matched against courier_meta.reference_id. Bookings made before
     * references existed sent the store's order id, which still matches as
     * `reference_id`.
     */
    public function parseWebhook(array $payload): array
    {
        if (!isset($payload['status_code']) || !is_scalar($payload['status_code'])) {
            return self::evidenceEvent($payload);
        }

        $timestamp = $payload['timestamp'] ?? null;
        $orderId = isset($payload['order_id']) && is_scalar($payload['order_id']) ? trim((string) $payload['order_id']) : '';

        return [array_filter([
            'reference_id'      => ctype_digit($orderId) ? $orderId : null,
            'courier_reference' => $orderId !== '' && !ctype_digit($orderId) ? mb_substr($orderId, 0, 64) : null,
            'tracking_number'   => isset($payload['tracking_no']) && is_scalar($payload['tracking_no']) ? (string) $payload['tracking_no'] : null,
            'status'            => (string) $payload['status_code'],
            'occurred_at'       => is_numeric($timestamp) ? date(DATE_ATOM, (int) $timestamp) : null,
            'remarks'           => isset($payload['message']) && is_scalar($payload['message']) ? mb_substr((string) $payload['message'], 0, 300) : null,
            'actual_fee'        => self::amount($payload['actual_shipping_fee'] ?? null),
            'meta'              => array_filter([
                'tracking_links'    => self::deliveryLink($payload['tracking_link'] ?? null),
                'edd'               => self::edd($payload['edd_min'] ?? null, $payload['edd_max'] ?? null, 'webhook'),
                'driver'            => array_filter([
                    'name'  => self::shortText($payload['driver_name'] ?? null, 100),
                    'phone' => self::shortText($payload['driver_phone_number'] ?? null, 32),
                ]) ?: null,
                'proofs'            => array_filter(['epod' => self::urls($payload['proof_of_delivery_list'] ?? null)]) ?: null,
                'actual_return_fee' => self::positiveAmount($payload['actual_return_shipping_fee'] ?? null),
            ], fn ($v) => $v !== null) ?: null,
        ], fn ($v, $k) => $v !== null || in_array($k, ['reference_id', 'tracking_number', 'occurred_at', 'remarks'], true), ARRAY_FILTER_USE_BOTH)];
    }

    /** The 4.2 proof lists, by the key each is stored under in meta.proofs. */
    private const EVIDENCE_LISTS = ['epod' => 'epod_list', 'epop' => 'epop_list', 'epor' => 'epor_list', 'epooh' => 'epooh_list'];

    /** 4.2 Evidence Proofs: matched by tracking_no, else customer_order_id (our order_id). */
    private static function evidenceEvent(array $payload): array
    {
        $proofs = [];
        foreach (self::EVIDENCE_LISTS as $key => $field) {
            if (($urls = self::urls($payload[$field] ?? null)) !== null) {
                $proofs[$key] = $urls;
            }
        }
        $tracking = isset($payload['tracking_no']) && is_scalar($payload['tracking_no']) ? (string) $payload['tracking_no'] : null;
        $reference = isset($payload['customer_order_id']) && is_scalar($payload['customer_order_id']) ? trim((string) $payload['customer_order_id']) : '';
        if ($proofs === [] || ($tracking === null && $reference === '')) {
            return [];
        }

        return [[
            'reference_id'      => ctype_digit($reference) ? $reference : null,
            'courier_reference' => $reference !== '' && !ctype_digit($reference) ? mb_substr($reference, 0, 64) : null,
            'tracking_number'   => $tracking,
            'status'            => null,
            'occurred_at'       => null,
            'remarks'           => null,
            'meta'              => ['proofs' => $proofs],
        ]];
    }

    /** The signature over the raw bytes SPX sent; see SpxClient::verifyWebhookSignature(). */
    public function verifyWebhookSignature(Request $request): bool
    {
        return $this->client->verifyWebhookSignature(
            $request->getContent(),
            $request->header('app-id'),
            $request->header('check-sign'),
            $request->header('timestamp'),
            $request->header('random-num'),
        );
    }

    // --- Links and cost -----------------------------------------------------------

    /**
     * SPX's tracking page for this parcel, as SPX gave it: from a webhook or
     * a tracking poll, else the create result's tracking_link. Never built
     * from the tracking number alone: SPX documents no public tracker address.
     */
    public function publicTrackingUrl(string $trackingNumber, array $courierMeta): ?string
    {
        foreach ([
            $courierMeta['tracking_links']['delivery'] ?? null,
            $courierMeta['booked_response']['tracking_link'] ?? null,
            $courierMeta['booked_response']['tracking_no_link'] ?? null,
        ] as $candidate) {
            if (($url = self::url($candidate)) !== null) {
                return $url;
            }
        }

        return null;
    }

    public function trackingLinks(Order $order): array
    {
        $url = $this->publicTrackingUrl((string) $order->courier_tracking_number, self::metaOf($order));

        return $url === null ? [] : [['label' => 'Track', 'url' => $url]];
    }

    /** SPX's actual fee once known (2.2, else the webhook's), else the fee quoted at booking. */
    public function actualShippingCost(array $courierMeta): ?float
    {
        foreach ([
            $courierMeta['actual_fee']['actual_shipping_fee'] ?? null,
            $courierMeta['webhook_actual_fee'] ?? null,
            $courierMeta['estimated_shipping_fee'] ?? null,
        ] as $fee) {
            if (($amount = self::amount($fee)) !== null && $amount > 0) {
                return $amount;
            }
        }

        return null;
    }

    // --- Settings ---------------------------------------------------------------

    /** 3.2 Check Account Credentials: no side effects, so it doubles as Test connection. */
    public function testConnection(): array
    {
        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'message' => 'App ID / App Secret not set.'];
        }
        if (!$this->client->hasAccountCredentials()) {
            return ['ok' => false, 'message' => 'User ID / User Secret not set.'];
        }

        try {
            $data = $this->client->verifyAccount();
        } catch (SpxApiException $e) {
            return ['ok' => false, 'message' => Str::limit("Failed: {$e->getMessage()} (ret_code {$e->retCode})", 157)];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'No answer from SPX.'];
        }

        return !empty($data['match_result'])
            ? ['ok' => true, 'message' => 'Connected. SPX reachable and credentials valid.']
            : ['ok' => false, 'message' => 'SPX reachable, but the User ID / User Secret pair did not match.'];
    }

    /**
     * 3.1 Create Account: issues the User ID / User Secret pair, using the
     * default pickup address's mobile number and the store's mail-from
     * address. Refused before any call while a User ID is on file: replacing
     * the pair would orphan every booking made under the old one.
     *
     * @return array{ok:bool, message?:string, error?:string}
     */
    public function createShipperAccount(): array
    {
        if (!empty(Setting::where('group', 'courier')->where('key', 'spx_user_id')->value('value'))) {
            return ['ok' => false, 'error' => 'An SPX shipper account is already on file (User ID set). Remove it first if you really need to replace it, so existing bookings are not silently orphaned.'];
        }
        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'error' => 'Set the App ID / App Secret before creating a shipper account.'];
        }

        $phone = PhPhone::normalize(PickupAddress::defaultAddress()?->contact_phone);
        if ($phone === null) {
            return ['ok' => false, 'error' => 'Set a valid PH mobile number on the default pickup address (Settings > Modules > Courier Fulfillment > Pickup addresses) before creating a shipper account.'];
        }

        $email = config('mail.from.address');

        try {
            $data = $this->client->createAccount($phone, is_string($email) && $email !== '' ? $email : null);
        } catch (SpxApiException $e) {
            return ['ok' => false, 'error' => "SPX account creation failed: {$e->getMessage()} (ret_code {$e->retCode})"];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'SPX account creation failed: SPX did not answer.'];
        }

        $userId = $data['user_id'] ?? null;
        $userSecret = $data['user_secret'] ?? null;
        if (!$userId || !$userSecret || !is_scalar($userId) || !is_scalar($userSecret)) {
            return ['ok' => false, 'error' => 'SPX did not return a user id/secret.'];
        }

        // Both or neither: a User ID without its secret reads as an account that cannot book.
        DB::transaction(function () use ($userId, $userSecret) {
            Setting::updateOrCreate(['group' => 'courier', 'key' => 'spx_user_id'], ['value' => (string) $userId]);
            Setting::updateOrCreate(['group' => 'courier', 'key' => 'spx_user_secret'], ['value' => Crypt::encryptString((string) $userSecret)]);
        });

        return ['ok' => true, 'message' => "Shipper account created (User ID {$userId})."];
    }

    /** The documented vocabulary, with no call to make. */
    public function refreshStatusLabels(): array
    {
        return ['ok' => true, 'labels' => self::STATUS_LABELS];
    }

    /** The same vocabulary without the refresh button; read through method_exists() by the queue and settings. */
    public function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    /**
     * 5.1 Get Address file download link, cached as the spx_address_tree
     * setting. The file is SPX's location spreadsheet: compile a newer one
     * with `php artisan courier:spx-locations-compile <file>`.
     *
     * @return array{ok:bool, url?:string, fetched_at?:string, error?:string}
     */
    public function refreshAddressTree(): array
    {
        if (!$this->client->hasAccountCredentials()) {
            return ['ok' => false, 'error' => 'User ID / User Secret not set. Cannot fetch the SPX address file.'];
        }

        try {
            $data = $this->client->getAddressDownloadUrl();
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Address file fetch failed: SPX did not answer.'];
        }

        $url = $data['address_download_url'] ?? null;
        if (!$url) {
            return ['ok' => false, 'error' => 'SPX did not return an address file URL.'];
        }

        $fetchedAt = now()->toIso8601String();
        Setting::updateOrInsert(
            ['group' => 'courier', 'key' => 'spx_address_tree'],
            ['value' => json_encode(['url' => $url, 'fetched_at' => $fetchedAt]), 'updated_at' => now()]
        );

        return ['ok' => true, 'url' => $url, 'fetched_at' => $fetchedAt];
    }

    public function mappableStatuses(): array
    {
        return array_keys(self::STATUS_LABELS);
    }

    /**
     * SPX issues one app pair and one user pair per integration; spx_mode
     * only switches the base URL. spx_pouch_presets holds a JSON list of
     * {label, length_cm, width_cm, height_cm, max_weight_kg}.
     */
    public function settingsSchema(): array
    {
        return [
            [
                'key'   => 'spx_enabled',
                'label' => 'Enable SPX',
                'type'  => 'toggle',
                'help'  => 'Book confirmed orders with SPX (Shopee Xpress) from the Fulfillment page.',
            ],
            [
                'key'     => 'spx_mode',
                'label'   => 'Mode',
                'type'    => 'select',
                'options' => [
                    ['value' => 'uat',        'label' => 'UAT'],
                    ['value' => 'production', 'label' => 'Production'],
                ],
            ],
            [
                'key'   => 'spx_app_id',
                'label' => 'App ID',
                'type'  => 'text',
                'help'  => 'Issued once per integration by SPX staff (not self-service).',
            ],
            [
                'key'       => 'spx_secret_key',
                'label'     => 'App Secret',
                'type'      => 'password',
                'encrypted' => true,
                'help'      => 'Signs every request (the check-sign header). Issued together with the App ID.',
            ],
            [
                'key'   => 'spx_user_id',
                'label' => 'User ID',
                'type'  => 'text',
                'help'  => 'SPX account id, obtained via Create Account.',
            ],
            [
                'key'       => 'spx_user_secret',
                'label'     => 'User Secret',
                'type'      => 'password',
                'encrypted' => true,
                'help'      => 'Required in the body of every order-related SPX call.',
            ],
            [
                'key'   => 'spx_pouch_presets',
                'label' => 'Pouch presets (JSON)',
                'type'  => 'text',
                'help'  => 'JSON array of {label, length_cm, width_cm, height_cm, max_weight_kg}. '
                    . 'Defaults: Small 25×20×10cm/3kg, Medium 35×25×15cm/5kg, Large 45×35×20cm/10kg. '
                    . 'The weight is a ceiling for that pouch; the booked weight is the parcel\'s own.',
            ],
        ];
    }

    // --- Pending creations ------------------------------------------------------

    /**
     * Finishes a `creating` booking, from courier:poll-status.
     *
     * With a batch number: 1.5 again. Settled with an order: its tracking
     * number, with the booked data written to the order's courier_meta.
     * Settled with a failure: `creation_failed`, SPX's raw words kept in
     * courier_meta.creation_error_courier. 13103 in the batch: the existing
     * order is looked up.
     *
     * Without one (an outcome never known): SPX is asked for the order by
     * its order_id. Not found within UNKNOWN_OUTCOME_MINUTES, the booking
     * becomes `creation_failed` with a message saying so; booking again then
     * reuses the same order_id, so SPX cannot create a second parcel.
     */
    public function resolvePendingCreation(Order $order): ?string
    {
        if (!$this->client->hasAccountCredentials()) {
            return null;
        }

        $meta = self::metaOf($order);
        $ref = is_string($meta['reference_id'] ?? null) ? $meta['reference_id'] : (string) $order->id;
        $batchNo = $meta['batch_no'] ?? null;

        if (!$batchNo) {
            if (($meta['outcome_unknown'] ?? false) !== true) {
                return null;
            }

            return $this->resolveUnknown($order, $meta, $ref);
        }

        try {
            $resultData = $this->client->getOrderCreateResult((int) $batchNo);
        } catch (\Throwable) {
            return null; // transient: the next poll tries again.
        }

        if (!is_array($resultData) || !in_array((int) ($resultData['task_status'] ?? 0), self::CREATE_RESULT_SETTLED, true)) {
            return null;
        }

        $outcome = self::outcomeFromCreateResult($resultData);
        if (!$outcome['ok'] && ($outcome['code'] ?? null) === 13103) {
            $found = $this->existingOrder($order, $ref, [], (int) $batchNo);
            if ($found !== null) {
                return $this->settle($order, $meta, $found['order'], $found['batch_no']);
            }
            $meta['outcome_unknown'] = true;
            $meta['unknown_since'] = now()->toIso8601String();
            unset($meta['batch_no']);
            $order->forceFill(['courier_meta' => json_encode($meta)])->save();

            return null;
        }
        if (!$outcome['ok']) {
            $this->markCreationFailed($order, $meta, $outcome['error'], $outcome['raw'] ?? null);
            Log::warning('SPX pending shipment creation failed', [
                'order' => $order->id, 'batch_no' => $batchNo, 'code' => $outcome['code'] ?? null,
            ]);

            return null;
        }

        return $this->settle($order, $meta, $outcome['order'], $batchNo);
    }

    private function resolveUnknown(Order $order, array $meta, string $ref): ?string
    {
        $found = $this->existingOrder($order, $ref, $meta, null);
        if ($found !== null) {
            return $this->settle($order, $meta, $found['order'], $found['batch_no']);
        }

        $since = is_string($meta['unknown_since'] ?? null) ? strtotime($meta['unknown_since']) : false;
        if ($since !== false && $since > now()->subMinutes(self::UNKNOWN_OUTCOME_MINUTES)->getTimestamp()) {
            return null;
        }

        $this->markCreationFailed(
            $order,
            $meta,
            "SPX never confirmed booking {$ref}, and no SPX order with that id could be found. Check the SPX portal. Booking again reuses {$ref}, so SPX cannot create a second parcel.",
            null,
        );

        return null;
    }

    /** Writes a settled booking's data to the order and hands back its tracking number. */
    private function settle(Order $order, array $meta, array $created, int|string|null $batchNo): ?string
    {
        $tracking = trim((string) ($created['tracking_no'] ?? ''));
        if ($tracking === '') {
            return null;
        }

        unset($meta['outcome_unknown'], $meta['unknown_since']);
        if ($batchNo !== null) {
            $meta['batch_no'] = $batchNo;
        }
        $meta = self::bookedMeta($created) + $meta;
        $order->forceFill(['courier_meta' => json_encode($meta)])->save();

        return $tracking;
    }

    private function markCreationFailed(Order $order, array $meta, string $error, ?string $raw): void
    {
        $meta['creation_failed'] = true;
        $meta['creation_error'] = $error;
        if ($raw !== null) {
            $meta['creation_error_courier'] = $raw;
        }
        $order->forceFill([
            'courier_status' => 'creation_failed',
            'courier_meta'   => json_encode($meta),
        ])->save();
    }

    /**
     * What a settled booking keeps: SPX's own 1.5 entry, its estimated and
     * remote-area fees, its tracking link and its delivery estimate.
     *
     * @return array<string, mixed>
     */
    private static function bookedMeta(array $created): array
    {
        return array_filter([
            'booked_response'        => $created,
            'estimated_shipping_fee' => self::amount($created['estimated_shipping_fee'] ?? null),
            'remote_area_fee'        => self::amount($created['remote_area_fee'] ?? null),
            'tracking_links'         => self::deliveryLink($created['tracking_link'] ?? $created['tracking_no_link'] ?? null),
            'edd'                    => self::edd($created['edd_min'] ?? $created['edd_info']['edd_min'] ?? null, $created['edd_max'] ?? $created['edd_info']['edd_max'] ?? null, 'booking'),
        ], fn ($v) => $v !== null);
    }

    // --- Booking edits ----------------------------------------------------------

    /**
     * 1.7 Update Order: the whole order again, built as createShipment()
     * builds it, plus its tracking number. What cannot change goes back as
     * booked: the service, collect type, who pays shipping, the COD amount
     * and the insured value (meta). The caller (SpxBookingEdits) checks the
     * status and the edit count and stores the result; this writes nothing.
     *
     * @param array<string, mixed> $edits parcel, weight_kg, length_cm, width_cm, height_cm, pickup_slot
     * @return array{ok:true}|array{ok:false, refusal:CourierRefusal, raw:?string}
     */
    public function updateBooking(Order $order, array $edits): array
    {
        $tracking = trim((string) $order->courier_tracking_number);
        $meta = self::metaOf($order);
        $ref = $meta['reference_id'] ?? null;
        if (!$this->client->hasAccountCredentials() || $tracking === '' || !is_string($ref) || $ref === '') {
            return ['ok' => false, 'refusal' => new CourierRefusal("SPX couldn't update this booking", 'It has no SPX reference on file.'), 'raw' => null];
        }

        $opts = array_merge(array_intersect_key($meta, array_flip([
            'service', 'parcel', 'shipping_payment', 'method', 'pickup_slot', 'weight_kg', 'length_cm', 'width_cm', 'height_cm',
        ])), array_intersect_key($edits, array_flip(['parcel', 'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'pickup_slot'])));
        $opts['reference'] = $ref;
        $opts['booked_cod_amount'] = $meta['cod_amount'] ?? 0;
        $opts['booked_cod'] = (bool) ($meta['cod'] ?? false);
        $opts['booked_insured_value'] = $meta['insured_value'] ?? 0;
        if (isset($meta['pickup_address']['id'])) {
            $opts['pickup_address_id'] = $meta['pickup_address']['id'];
        }
        if (($opts['method'] ?? 'dropoff') !== 'pickup') {
            unset($opts['pickup_slot']);
        }

        $built = $this->buildOrderPayload($order, $opts);
        if (!$built['ok']) {
            return ['ok' => false, 'refusal' => new CourierRefusal("SPX can't update this booking", $built['error']), 'raw' => null];
        }
        if (($problem = $this->limitProblem($order, $opts)) !== null) {
            return ['ok' => false, 'refusal' => new CourierRefusal("SPX can't update this booking", $problem), 'raw' => null];
        }

        $context = $this->parcelContext($order, $opts);
        try {
            $data = $this->client->updateOrders([['tracking_no' => $tracking] + $built['payload']]);
        } catch (SpxApiException $e) {
            $raw = self::rawText($e->retCode, $e->getMessage(), null);

            return ['ok' => false, 'raw' => $raw, 'refusal' => !$e->isServerError() && SpxRefusals::isRefusal($e->retCode)
                ? self::updateRefusal(SpxRefusals::explain($e->retCode, $e->getMessage(), $context))
                : new CourierRefusal("SPX couldn't update this booking", 'Try again in a moment.')];
        } catch (\Throwable $e) {
            Log::warning('SPX update order could not reach SPX', ['order' => $order->id, 'exception' => $e::class]);

            return ['ok' => false, 'refusal' => new CourierRefusal("SPX couldn't update this booking", 'Try again in a moment.'), 'raw' => null];
        }

        $failed = ($data['fail_list'] ?? [])[0] ?? null;
        if (is_array($failed)) {
            $code = is_numeric($failed['ret_code'] ?? null) ? (int) $failed['ret_code'] : null;
            $text = trim(((string) ($failed['message'] ?? '')) . ' ' . ((string) ($failed['debug_msg'] ?? '')));

            return ['ok' => false, 'refusal' => self::updateRefusal(SpxRefusals::explain($code, $text, $context)), 'raw' => self::createFailure($failed, $context)['raw']];
        }
        if (!is_array(($data['orders'] ?? [])[0] ?? null)) {
            return ['ok' => false, 'refusal' => new CourierRefusal("SPX couldn't update this booking", 'Try again in a moment.'), 'raw' => null];
        }

        return ['ok' => true, 'payload' => $built['payload']];
    }

    /** SPX's generic sentence says "book"; an update says "update". */
    private static function updateRefusal(CourierRefusal $refusal): CourierRefusal
    {
        return $refusal->title === SpxRefusals::GENERIC_TITLE ? new CourierRefusal("SPX couldn't update this booking", $refusal->detail) : $refusal;
    }

    // --- Payload builders ---------------------------------------------------------

    /**
     * Why SPX would refuse this parcel, checked before anything is sent: a
     * known weight under 50 kg, within the chosen pouch's ceiling, sides of
     * at most 150 cm, and COD in whole pesos up to 50,000. SPX's fee check
     * then applies its own, possibly stricter, rules.
     */
    private function limitProblem(Order $order, array $opts): ?string
    {
        $dims = $this->resolveParcelDims($opts);
        if (!$dims['ok']) {
            return $dims['error'];
        }

        $weight = self::typedWeight($order, $opts);
        if ($weight === null) {
            return 'Enter the parcel weight. None of the products has a weight recorded.';
        }
        if ($weight >= self::MAX_WEIGHT_KG) {
            return 'SPX weight must be under 50 kg.';
        }
        $ceiling = $dims['dims']['max_weight_kg'];
        if ($ceiling !== null && $weight > $ceiling + 0.0001) {
            return sprintf(
                'The parcel weighs %s kg, more than the %s pouch takes (up to %s kg). Choose a bigger parcel or enter its size.',
                CourierRefusal::number(self::plain($weight)),
                $dims['dims']['label'] ?? 'chosen',
                CourierRefusal::number(self::plain($ceiling))
            );
        }

        foreach (['length_cm', 'width_cm', 'height_cm'] as $side) {
            if ($dims['dims'][$side] > self::MAX_SIDE_CM) {
                return "SPX sides can't exceed 150 cm.";
            }
        }

        $collect = $this->codAmount($order, $opts);
        if ($collect !== null) {
            if (abs($collect - round($collect)) > 0.001) {
                return 'SPX collects COD in whole pesos only, and this order totals ₱' . number_format($collect, 2) . '. Adjust the order total, or book with another courier.';
            }
            if ($collect > self::MAX_COD) {
                return 'SPX collects COD up to ₱50,000 only.';
            }
            if ($collect <= 0) {
                return 'A COD order needs an amount to collect above zero.';
            }
        }

        return null;
    }

    /** Whether the rider collects: the caller's buyer_payment when given, else the store's COD mapping. */
    private static function isCod(Order $order, array $opts): bool
    {
        if (array_key_exists('booked_cod', $opts)) {
            return (bool) $opts['booked_cod'];
        }
        $said = $opts['buyer_payment'] ?? null;

        return $said !== null && $said !== '' ? $said === 'cod' : FulfillmentService::isCodOrder($order);
    }

    /**
     * What the rider collects, or null when nothing is: the order total,
     * shipping the buyer paid included (the same amount LBC collects). An
     * edited booking keeps the amount it was booked with.
     */
    private function codAmount(Order $order, array $opts): ?float
    {
        if (!self::isCod($order, $opts)) {
            return null;
        }
        if (array_key_exists('booked_cod_amount', $opts)) {
            return round((float) $opts['booked_cod_amount'], 2);
        }

        return round((float) $order->total, 2);
    }

    /**
     * The value SPX insures: the order total when the merchant ticks Insure,
     * as QuadX declares it, else 0 (sent explicitly, never left to SPX's
     * default). An edited booking keeps its booked value.
     */
    private static function insuredValue(Order $order, array $opts): float
    {
        if (array_key_exists('booked_insured_value', $opts)) {
            return round((float) $opts['booked_insured_value'], 2);
        }

        return filter_var($opts['insure'] ?? false, FILTER_VALIDATE_BOOLEAN) ? round((float) $order->total, 2) : 0.0;
    }

    /** The typed weight, else the products' own weights; null when neither is known. */
    private static function typedWeight(Order $order, array $opts): ?float
    {
        $typed = $opts['weight_kg'] ?? null;
        if (is_numeric($typed) && (float) $typed > 0) {
            return round((float) $typed, 3);
        }

        return OrderWeight::kg($order);
    }

    /**
     * `kind` and `code` are set when SPX's location list itself rules the
     * address out, so checkServiceability() can say so without asking SPX.
     *
     * @return array{ok:bool, payload?:array, error?:string, kind?:string, code?:string}
     */
    private function buildOrderPayload(Order $order, array $opts): array
    {
        $sender = $this->senderInfo($opts);
        if (!$sender['ok']) {
            return ['ok' => false, 'error' => $sender['error']] + array_intersect_key($sender, ['kind' => 1, 'code' => 1]);
        }

        $deliver = $this->deliverInfo($order);
        if (!$deliver['ok']) {
            return ['ok' => false, 'error' => $deliver['error']] + array_intersect_key($deliver, ['kind' => 1, 'code' => 1]);
        }

        $fulfillment = $this->fulfillmentInfo($order, $opts);
        // A pickup is checked as collected even before it has a window (a fee check).
        $collected = $fulfillment['collect_type'] === 1 || ($opts['method'] ?? null) === 'pickup';
        if (($refusal = self::supportRefusal($sender['flags'], $deliver['flags'], (bool) $fulfillment['cod_collection'], $collected)) !== null) {
            return ['ok' => false] + $refusal;
        }

        $dims = $this->resolveParcelDims($opts);
        if (!$dims['ok']) {
            return ['ok' => false, 'error' => $dims['error']];
        }

        // The real weight. A preset's ceiling stands in only where no weight
        // can be known yet (the coverage from-price before a parcel is
        // chosen); createShipment() refuses a booking without one.
        $weight = self::typedWeight($order, $opts) ?? $dims['dims']['max_weight_kg'];
        if ($weight === null) {
            return ['ok' => false, 'error' => 'Enter the parcel weight.'];
        }
        $dims['dims']['weight_kg'] = (float) $weight;

        return ['ok' => true, 'payload' => [
            'order_id'         => (string) ($opts['reference'] ?? self::newReference()),
            'base_info'        => ['service_type' => 1],
            'sender_info'      => $sender['info'],
            'fulfillment_info' => $fulfillment,
            'deliver_info'     => $deliver['info'],
            'parcel_info'      => $this->parcelInfo($order, $dims['dims'], $opts),
        ]];
    }

    /**
     * Sender = the pickup address chosen for this booking (or the default),
     * placed on SPX's location list. detail_address is the street text.
     *
     * @return array{ok:bool, info?:array, flags?:string, error?:string, kind?:string, code?:string}
     */
    private function senderInfo(array $opts = []): array
    {
        $pickup = PickupAddress::resolveFor($opts);

        $state    = trim((string) ($pickup->state ?? ''));
        $city     = trim((string) ($pickup->city ?? ''));
        $district = trim((string) ($pickup->district ?? ''));
        $barangay = trim((string) ($pickup->barangay ?? ''));
        $address  = SpxFieldLimits::detailAddress($pickup->address_1 ?? null, $pickup?->getAttribute('address_2'));
        $postCode = trim((string) ($pickup->postcode ?? ''));
        $name     = trim((string) ($pickup->contact_name ?? '')) ?: 'Sender';
        $phone    = PhPhone::normalize($pickup->contact_phone ?? '');

        if ($state === '' || $city === '' || $barangay === '' || $address === '' || $postCode === '' || $phone === null) {
            return ['ok' => false, 'code' => 'missing_pickup_address', 'error' => 'Set a complete pickup address (Settings > Modules > Courier Fulfillment > Pickup addresses: province, city, barangay, address, postal code, a valid PH mobile number) before booking with SPX.'];
        }
        if (mb_strlen($name) > SpxFieldLimits::NAME_MAX) {
            return ['ok' => false, 'error' => 'The pickup contact name is longer than SPX takes (' . SpxFieldLimits::NAME_MAX . ' characters). Shorten it on the pickup address.'];
        }
        if (mb_strlen($address) > SpxFieldLimits::DETAIL_ADDRESS_MAX) {
            return ['ok' => false, 'error' => 'The pickup street address is longer than SPX takes (' . SpxFieldLimits::DETAIL_ADDRESS_MAX . ' characters). Shorten it on the pickup address.'];
        }

        $spx = $this->locations()->resolve(['state' => $state, 'city' => $city, 'district' => $district, 'barangay' => $barangay]);
        if (!$spx['ok']) {
            return ['ok' => false, 'error' => "SPX doesn't list the pickup address's {$spx['missing']} ({$barangay}, {$city}, {$state}). Check its spelling on the pickup address.", 'kind' => ServiceabilityVerdict::KIND_ORIGIN, 'code' => 'spx_unlisted_pickup_' . $spx['missing']];
        }
        $at = $spx['location'];

        return ['ok' => true, 'flags' => $spx['flags'], 'info' => [
            'sender_state'          => $at['state'],
            'sender_city'           => $at['city'],
            'sender_district'       => $at['district'],
            'sender_street'         => $at['street'],
            'sender_post_code'      => $postCode,
            'sender_name'           => $name,
            'sender_phone'          => $phone,
            'sender_detail_address' => $address,
        ]];
    }

    /**
     * Deliver = the order's shipping address, placed on SPX's location list
     * as senderInfo() places the pickup address. An order with no barangay,
     * or one SPX does not list, is refused rather than sent with a stand-in.
     *
     * @return array{ok:bool, info?:array, flags?:string, error?:string, kind?:string, code?:string}
     */
    private function deliverInfo(Order $order): array
    {
        $state    = trim((string) $order->shipping_state);
        $city     = trim((string) $order->shipping_city);
        $district = trim((string) $order->shipping_district);
        $barangay = trim((string) $order->shipping_barangay);
        $postCode = trim((string) $order->shipping_postcode);
        $address  = SpxFieldLimits::detailAddress($order->shipping_address_1, $order->getAttribute('shipping_address_2'));
        $phone    = PhPhone::normalize($order->shipping_phone ?: $order->phone);
        $name     = SpxFieldLimits::fullName($order->shipping_first_name, $order->shipping_last_name) ?: 'Customer';

        foreach (['province' => $state, 'city' => $city, 'barangay' => $barangay, 'postal code' => $postCode] as $field => $value) {
            if ($value === '') {
                return ['ok' => false, 'code' => 'missing_' . str_replace(' ', '', $field), 'error' => "This order's shipping address has no {$field}. Add it on the order before booking with SPX."];
            }
        }
        if ($phone === null) {
            return ['ok' => false, 'code' => 'missing_phone', 'error' => "This order's phone number isn't a valid PH mobile number (11 digits starting with 0, or 12 starting with 63). Correct it on the order before booking with SPX."];
        }
        if (mb_strlen($name) > SpxFieldLimits::NAME_MAX) {
            return ['ok' => false, 'error' => "The customer's name is longer than SPX takes (" . SpxFieldLimits::NAME_MAX . ' characters). Shorten it on the order.'];
        }
        if (mb_strlen($address) > SpxFieldLimits::DETAIL_ADDRESS_MAX) {
            return ['ok' => false, 'error' => "The customer's street address is longer than SPX takes (" . SpxFieldLimits::DETAIL_ADDRESS_MAX . ' characters). Shorten it on the order.'];
        }

        $spx = $this->locations()->resolve(['state' => $state, 'city' => $city, 'district' => $district, 'barangay' => $barangay]);
        if (!$spx['ok']) {
            return ['ok' => false, 'error' => "SPX doesn't list the customer's {$spx['missing']} ({$barangay}, {$city}, {$state}).", 'kind' => ServiceabilityVerdict::KIND_DESTINATION, 'code' => 'spx_unlisted_' . $spx['missing']];
        }
        $at = $spx['location'];

        $info = [
            'deliver_state'          => $at['state'],
            'deliver_city'           => $at['city'],
            'deliver_district'       => $at['district'],
            'deliver_street'         => $at['street'],
            'deliver_post_code'      => $postCode,
            'deliver_name'           => $name,
            'deliver_phone'          => $phone,
            // detail_address is required; the barangay stands in for an empty street line.
            'deliver_detail_address' => $address !== '' ? $address : $barangay,
        ];
        // Optional receiver remark: the buyer's message to the courier, when there is one.
        $instruction = trim((string) $order->courier_comment);
        if ($instruction !== '') {
            $info['deliver_instruction'] = SpxFieldLimits::cut($instruction, SpxFieldLimits::INSTRUCTION_MAX);
        }

        return ['ok' => true, 'info' => $info, 'flags' => $spx['flags']];
    }

    private function locations(): SpxLocations
    {
        return $this->locations ??= new SpxLocations();
    }

    /**
     * The location flags this booking needs: delivery (and COD delivery when
     * COD) at the customer's barangay, pickup (and COD pickup when COD) at
     * the sender's when the parcel is collected rather than dropped off.
     *
     * @return array{error:string, kind:string, code:string}|null
     */
    private static function supportRefusal(string $senderFlags, string $deliverFlags, bool $cod, bool $pickup): ?array
    {
        $checks = [
            [$deliverFlags, SpxLocations::DELIVER, true, "SPX doesn't deliver to this barangay.", ServiceabilityVerdict::KIND_DESTINATION, 'spx_no_delivery'],
            [$deliverFlags, SpxLocations::COD_DELIVER, $cod, "SPX doesn't collect COD in this barangay.", ServiceabilityVerdict::KIND_COD, 'spx_no_cod'],
            [$senderFlags, SpxLocations::PICKUP, $pickup, "SPX doesn't pick up from the pickup address's barangay. Choose drop-off.", ServiceabilityVerdict::KIND_ORIGIN, 'spx_no_pickup'],
            [$senderFlags, SpxLocations::COD_PICKUP, $pickup && $cod, "SPX doesn't pick up COD parcels from the pickup address's barangay. Choose drop-off.", ServiceabilityVerdict::KIND_ORIGIN, 'spx_no_cod_pickup'],
        ];
        foreach ($checks as [$flags, $flag, $needed, $error, $kind, $code]) {
            if ($needed && !SpxLocations::supports($flags, $flag)) {
                return ['error' => $error, 'kind' => $kind, 'code' => $code];
            }
        }

        return null;
    }

    /** payment_role, COD and collect type, with the pickup window when collected. */
    private function fulfillmentInfo(Order $order, array $opts): array
    {
        $collect = $this->codAmount($order, $opts);
        $info = [
            'payment_role'   => ($opts['shipping_payment'] ?? null) === 'receiver_pay' ? 2 : 1,
            'cod_collection' => $collect !== null ? 1 : 0,
        ];
        if ($collect !== null) {
            // Whole pesos: limitProblem() refuses anything else before a booking is sent.
            $info['cod_amount'] = (int) round($collect);
        }

        $slot = ($opts['method'] ?? null) === 'dropoff' ? null : self::decodeSlot($opts['pickup_slot'] ?? null);
        if ($slot !== null) {
            $info['collect_type']         = 1;
            $info['pickup_time']          = $slot['pickup_time'];
            $info['pickup_time_range_id'] = $slot['pickup_time_range_id'];
            $info['pickup_time_range']    = $slot['pickup_time_range'];
        } else {
            $info['collect_type'] = 2;
        }

        return $info;
    }

    private function parcelInfo(Order $order, array $dims, array $opts): array
    {
        $products = $order->products;
        $qty = 0;
        foreach ($products as $p) {
            $qty += (int) $p->quantity;
        }

        $firstName = $products->first()?->name;

        return [
            'parcel_weight'         => $dims['weight_kg'],
            'parcel_length'         => $dims['length_cm'],
            'parcel_width'          => $dims['width_cm'],
            'parcel_height'         => $dims['height_cm'],
            'parcel_item_name'      => $firstName ? Str::limit($firstName, 60, '') : ('Order #' . $order->id),
            'parcel_item_quantity'  => max(1, $qty),
            'express_insured_value' => self::insuredValue($order, $opts),
            'parcel_item_type'      => self::PARCEL_ITEM_TYPE,
        ];
    }

    /**
     * The parcel's size: typed dimensions when all three are given (they win
     * over a preset's), a preset's when chosen, else the first preset. A
     * preset also gives its weight ceiling; typed sizes have none.
     *
     * @return array{ok:bool, dims?:array{length_cm:float,width_cm:float,height_cm:float,max_weight_kg:?float,label?:string}, error?:string}
     */
    private function resolveParcelDims(array $opts): array
    {
        $key = $opts['parcel'] ?? null;

        $typed = [$opts['length_cm'] ?? null, $opts['width_cm'] ?? null, $opts['height_cm'] ?? null];
        $allTyped = count(array_filter($typed, fn ($d) => is_numeric($d) && (float) $d > 0)) === 3;
        if ($allTyped) {
            return ['ok' => true, 'dims' => [
                'length_cm' => (float) $typed[0], 'width_cm' => (float) $typed[1],
                'height_cm' => (float) $typed[2], 'max_weight_kg' => null,
            ]];
        }
        if ($key === 'custom') {
            return ['ok' => false, 'error' => 'Custom parcel dimensions (length, width and height) are required and must be greater than zero.'];
        }

        $presets = $this->pouchPresets();
        $chosen = null;
        foreach ($presets as $preset) {
            if (Str::slug((string) ($preset['label'] ?? '')) === $key) {
                $chosen = $preset;
                break;
            }
        }
        // An unknown or missing value (a stale option, another courier's
        // parcel) falls back to the first preset.
        $chosen ??= $presets[0] ?? self::DEFAULT_POUCH_PRESETS[0];

        return ['ok' => true, 'dims' => [
            'length_cm' => (float) ($chosen['length_cm'] ?? 0), 'width_cm' => (float) ($chosen['width_cm'] ?? 0),
            'height_cm' => (float) ($chosen['height_cm'] ?? 0),
            'max_weight_kg' => is_numeric($chosen['max_weight_kg'] ?? null) ? (float) $chosen['max_weight_kg'] : null,
            'label' => (string) ($chosen['label'] ?? 'chosen'),
        ]];
    }

    private function pouchPresets(): array
    {
        $raw = Setting::where('group', 'courier')->where('key', 'spx_pouch_presets')->value('value');
        $decoded = $raw ? json_decode($raw, true) : null;

        return (is_array($decoded) && $decoded !== []) ? array_values(array_filter($decoded, 'is_array')) : self::DEFAULT_POUCH_PRESETS;
    }

    /**
     * The parcel figures, so a refusal can name them ("Length is 35 cm.").
     *
     * @return array<string, float>
     */
    private function parcelContext(Order $order, array $opts): array
    {
        $context = [];
        $dims = $this->resolveParcelDims($opts);
        if ($dims['ok']) {
            foreach (['length_cm', 'width_cm', 'height_cm'] as $side) {
                $context[$side] = $dims['dims'][$side];
            }
        }
        if (($weight = self::typedWeight($order, $opts)) !== null) {
            $context['weight_kg'] = $weight;
        }

        return $context;
    }

    // --- Async create polling -----------------------------------------------------

    /**
     * Polls 1.5 for up to about 15 seconds (backoff 1, 2, 3, 4, 5 s), so Book
     * never hangs on SPX's async create. Null when still running.
     */
    private function pollCreateResult(int $batchNo): ?array
    {
        $backoffMs = [1000, 2000, 3000, 4000, 5000];
        $attempts = count($backoffMs) + 1;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $result = $this->client->getOrderCreateResult($batchNo);
            } catch (\Throwable) {
                $result = null;
            }

            if (is_array($result) && in_array((int) ($result['task_status'] ?? 0), self::CREATE_RESULT_SETTLED, true)) {
                return $result;
            }
            if ($i < $attempts - 1) {
                ($this->sleeper)($backoffMs[$i]);
            }
        }

        return null;
    }

    /**
     * One fail_list entry: `error` is the merchant's sentence, `raw` is SPX's
     * code and its own words, kept for support and never shown as the error.
     *
     * @return array{error:string, raw:?string}
     */
    private static function createFailure(array $fail, array $context = []): array
    {
        $code = is_numeric($fail['ret_code'] ?? null) ? (int) $fail['ret_code'] : null;
        $message = self::cleanText($fail['message'] ?? null);
        $debug = self::cleanText($fail['debug_msg'] ?? null);

        return [
            'error' => SpxRefusals::explain($code, trim(($message ?? '') . ' ' . ($debug ?? '')), $context)->message(),
            'raw'   => self::rawText($code, $message, $debug),
        ];
    }

    /** SPX's own answer on one line for support: "SPX 13101: <message> | <debug_msg>". */
    private static function rawText(?int $code, ?string $message, ?string $debug): ?string
    {
        $words = implode(' | ', array_filter([self::cleanText($message), self::cleanText($debug)]));
        if ($code === null && $words === '') {
            return null;
        }

        return $code === null ? "SPX: {$words}" : ($words === '' ? "SPX {$code}" : "SPX {$code}: {$words}");
    }

    /** SPX's text on one line, at most 300 characters. */
    private static function cleanText(mixed $text): ?string
    {
        if (!is_string($text)) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : mb_substr($text, 0, 300);
    }

    /**
     * Success is never read from task_status alone: "if all orders failed to
     * be created, the task status is still Done". fail_list, fail_count and
     * task_status 5 all mean no usable order.
     *
     * @return array{ok:bool, order?:array, error?:string, raw?:?string, code?:?int}
     */
    private static function outcomeFromCreateResult(array $resultData, array $context = []): array
    {
        $taskFailed = (int) ($resultData['task_status'] ?? 0) === 5;
        $failCount  = (int) ($resultData['fail_count'] ?? 0);
        $failList   = $resultData['fail_list'] ?? [];
        $orders     = $resultData['orders'] ?? [];

        if ($taskFailed || $failCount > 0 || !empty($failList) || empty($orders)) {
            $first = is_array($failList[0] ?? null) ? $failList[0] : null;
            if ($first !== null) {
                $failure = self::createFailure($first, $context);

                return ['ok' => false, 'error' => $failure['error'], 'raw' => $failure['raw'], 'code' => is_numeric($first['ret_code'] ?? null) ? (int) $first['ret_code'] : null];
            }

            $said = $taskFailed && is_string($resultData['description'] ?? null) && $resultData['description'] !== ''
                ? 'SPX: batch failed | ' . self::cleanText($resultData['description'])
                : null;

            return ['ok' => false, 'error' => SpxRefusals::generic()->message(), 'raw' => $said, 'code' => null];
        }

        $created = $orders[0];
        if (!is_array($created) || empty($created['tracking_no'])) {
            return ['ok' => false, 'error' => 'SPX reported success but returned no tracking number.', 'code' => null];
        }

        return ['ok' => true, 'order' => $created];
    }

    // --- Small value helpers ------------------------------------------------------

    /** @return array{delivery:string}|null */
    private static function deliveryLink(mixed $url): ?array
    {
        $url = self::url($url);

        return $url !== null ? ['delivery' => $url] : null;
    }

    /** An http(s) URL of sane length, or null. */
    private static function url(mixed $url): ?string
    {
        if (!is_string($url)) {
            return null;
        }
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    /**
     * SPX's delivery estimate as SPX gives it. Both zero is SPX's "none".
     *
     * @return array{min:?int, max:?int, source:string}|null
     */
    private static function edd(mixed $min, mixed $max, string $source): ?array
    {
        $min = is_numeric($min) ? (int) $min : null;
        $max = is_numeric($max) ? (int) $max : null;
        if (($min === null && $max === null) || (($min ?? 0) === 0 && ($max ?? 0) === 0)) {
            return null;
        }

        return ['min' => $min, 'max' => $max, 'source' => $source];
    }

    /** Proof URLs, http(s) only, at most 20, duplicates dropped. */
    private static function urls(mixed $list): ?array
    {
        if (!is_array($list)) {
            return null;
        }
        $urls = [];
        foreach ($list as $item) {
            if (($url = self::url($item)) !== null && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
            if (count($urls) >= 20) {
                break;
            }
        }

        return $urls === [] ? null : $urls;
    }

    private static function shortText(mixed $value, int $max): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** A non-negative money amount rounded to centavos, or null. */
    private static function amount(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value >= 0 && is_finite((float) $value) ? round((float) $value, 2) : null;
    }

    /** An amount above zero, else null: SPX answers 0 for a fee it has not set. */
    private static function positiveAmount(mixed $value): ?float
    {
        $amount = self::amount($value);

        return $amount !== null && $amount > 0 ? $amount : null;
    }

    private static function plain(float $n): string
    {
        return rtrim(rtrim(sprintf('%.3F', $n), '0'), '.') ?: '0';
    }
}
