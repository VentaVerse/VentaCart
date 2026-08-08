<?php

namespace Extensions\CourierFulfillment\Couriers\Spx;

use App\Models\Order;
use App\Models\Setting;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;

/**
 * SPX (Shopee Xpress) courier — Task T1 of the fulfillment-workflow-v2 + SPX
 * plan (`docs/superpowers/plans/2026-08-08-spx-courier.md`). This task only
 * wires the signed client, the settings schema/registration, and
 * testConnection() — every method that actually talks to an order
 * (createShipment/cancelShipment/getTracking/printLabel) is a deliberate
 * stub, filled in by Task T2/T3. Ground truth for every SPX shape is
 * docs/spx/integration-brief.md + docs/spx/api.md — never invented here.
 *
 * Booking/cancel/tracking/print are genuine user-action paths, so they throw
 * a clear "not implemented yet" exception rather than silently no-op'ing.
 * Everything else the queue/settings pages call unconditionally for ANY
 * registered courier (service/parcel/payment option lists, the estimate/
 * pickup-slot v2 methods, status buckets, webhook parsing, tracking links,
 * status refresh) returns the interface's own "unsupported" default instead
 * — so registering SPX today can never break the queue or settings pages
 * for QuadX, even if spx_enabled is flipped on prematurely.
 */
class SpxFulfillment implements CourierFulfillmentInterface
{
    /**
     * Sensible default PH pouch dimensions (T1 placeholder, editable via the
     * spx_pouch_presets setting — see settingsSchema()). Well inside SPX's
     * documented PH parcel limits (integration-brief.md §8: weight < 50kg,
     * each dimension <= 150cm). Small/Medium/Large mirrors the same shopper
     * mental model QuadX's pouch parcel options already use.
     */
    public const DEFAULT_POUCH_PRESETS = [
        ['label' => 'Small',  'length_cm' => 25, 'width_cm' => 20, 'height_cm' => 10, 'max_weight_kg' => 3],
        ['label' => 'Medium', 'length_cm' => 35, 'width_cm' => 25, 'height_cm' => 15, 'max_weight_kg' => 5],
        ['label' => 'Large',  'length_cm' => 45, 'width_cm' => 35, 'height_cm' => 20, 'max_weight_kg' => 10],
    ];

    public function __construct(
        private ?SpxClient $client = null,
    ) {
        $this->client = $client ?? new SpxClient();
    }

    public function getKey(): string
    {
        return 'spx';
    }

    public function getName(): string
    {
        return 'SPX';
    }

    /** Flag on AND app-level credential completeness (app-id + secret), mirroring QuadX's pattern. */
    public function isEnabled(): bool
    {
        $on = Setting::where('group', 'courier')->where('key', 'spx_enabled')->value('value');
        return (bool) $on && $this->client->isConfigured();
    }

    /** T2 fills this from SPX's own service_type vocabulary. */
    public function getServiceTypes(): array
    {
        return [];
    }

    /** T2 fills this from the configured spx_pouch_presets (+ a custom-dims escape). */
    public function getParcelOptions(): array
    {
        return [];
    }

    /** T2 fills this from SPX's payment_role (sender-pay/receiver-pay). */
    public function getShippingPaymentOptions(): array
    {
        return [];
    }

    /** User-action path — genuinely not implemented until Task T2. */
    public function createShipment(Order $order, array $opts): array
    {
        throw new \RuntimeException('SPX::createShipment() is not implemented yet — see Task T2 of the fulfillment-workflow-v2 + SPX plan.');
    }

    /** User-action path — genuinely not implemented until Task T2. */
    public function cancelShipment(string $trackingNumber): array
    {
        throw new \RuntimeException('SPX::cancelShipment() is not implemented yet — see Task T2 of the fulfillment-workflow-v2 + SPX plan.');
    }

    /** User-action path — genuinely not implemented until Task T2. */
    public function getTracking(string $trackingNumber): array
    {
        throw new \RuntimeException('SPX::getTracking() is not implemented yet — see Task T2 of the fulfillment-workflow-v2 + SPX plan.');
    }

    /** T2 fills this from integration-brief.md's status-code table (§5). */
    public function terminalStatuses(): array
    {
        return [];
    }

    /** T2 fills this ("pending pickup/dropoff" per the docs' 13002 constraint). */
    public function cancelableStatuses(): array
    {
        return [];
    }

    /**
     * Never called with a real SPX status yet (no booking path exists until
     * T2) — a neutral, non-throwing placeholder so the queue/poll never
     * crash if this courier is ever enabled before T2 lands. T2 replaces
     * this with the real 8-phase bucketing off integration-brief.md's status
     * table, mirroring QuadXFulfillment::statusPhase().
     */
    public function statusPhase(?string $status): array
    {
        return ['phase' => 'to_handover', 'step' => 1, 'failed' => false];
    }

    /**
     * SPX quotes fees via 2.1 Check Shipping Fee (batch_check_order) — T2's
     * job. Sanctioned interface default: null = "no estimate available".
     */
    public function estimateFee(Order $order, array $options): ?object
    {
        return null;
    }

    /**
     * SPX schedules pickup via 1.1 Get Pickup Timeslot — T2's job.
     * Sanctioned interface default: [] = "no slot picker".
     */
    public function getPickupSlots(Order $order): array
    {
        return [];
    }

    /** User-action path — genuinely not implemented until Task T3 (AWB storage). */
    public function printLabel(string $trackingNumber, string $format): array
    {
        throw new \RuntimeException('SPX::printLabel() is not implemented yet — see Task T3 of the fulfillment-workflow-v2 + SPX plan.');
    }

    /** T3 fills this (signed webhook dispatch, status_code -> event mapping). */
    public function parseWebhook(array $payload): array
    {
        return [];
    }

    /** No bookings exist yet (createShipment() isn't implemented) — nothing to link to. */
    public function trackingLinks(Order $order): array
    {
        return [];
    }

    /**
     * The one real capability this task ships: SPX's 3.2 Check Account
     * Credentials (`POST /open/api/v1/account/verify`) is the cheapest
     * authenticated call in the whole API surface — no order side-effects —
     * so it doubles as "test connection". Graceful (never throws) when
     * either credential pair is missing, and on any SPX-side failure.
     */
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
            return ['ok' => false, 'message' => "Failed: {$e->getMessage()} (ret_code {$e->retCode})"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Failed: ' . $e->getMessage()];
        }

        return !empty($data['match_result'])
            ? ['ok' => true, 'message' => 'Connected — SPX reachable and credentials valid.']
            : ['ok' => false, 'message' => 'SPX reachable, but the User ID / User Secret pair did not match.'];
    }

    /** T2/T3 fills this from SPX's live status vocabulary — no such endpoint exists in the docs yet, so this stays a graceful no-op. */
    public function refreshStatusLabels(): array
    {
        return ['ok' => false, 'error' => 'SPX status refresh is not implemented yet.'];
    }

    /** T2 fills this from integration-brief.md's status-code table (§5). */
    public function mappableStatuses(): array
    {
        return [];
    }

    /**
     * spx_enabled, spx_mode, spx_app_id, spx_secret_key, spx_user_id,
     * spx_user_secret, spx_pouch_presets — exactly the keys specified by
     * Task T1's brief, in the order they read most naturally on the page.
     * Unlike QuadX, SPX's app-id/app-secret and user-id/user-secret are NOT
     * mode_scoped: SPX issues one set of each per integration
     * (integration-brief.md §2) and `spx_mode` only switches which base URL
     * SpxClient talks to — there's no separate UAT vs production credential
     * pair to declare.
     *
     * spx_pouch_presets has no first-class JSON/structured field type in
     * this interface yet (only text|password|select|toggle exist — see
     * settingsSchema()'s docblock) so it's declared as `type: 'text'`
     * holding a raw JSON string of `{label,length_cm,width_cm,height_cm,
     * max_weight_kg}` objects (see DEFAULT_POUCH_PRESETS above for the
     * shape + the Small/Medium/Large defaults). Task T4 is expected to
     * replace this with a proper structured editor.
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
                'help'  => 'SPX account id, obtained via 3.1 Create Account.',
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
                    . 'No structured editor yet (Task T4) — edit the raw JSON here.',
            ],
        ];
    }
}
