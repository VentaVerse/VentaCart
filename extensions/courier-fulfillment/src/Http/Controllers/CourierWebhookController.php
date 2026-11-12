<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Extensions\CourierFulfillment\Jobs\ProcessCourierWebhookJob;
use Extensions\CourierFulfillment\Support\CourierWebhookIngestor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Generic courier webhook receiver: /webhooks/courier/{provider}?token=...
 *
 * The provider segment routes to that courier's parseWebhook(), which returns
 * normalized events. We match each event back to a Venta order by reference_id
 * (we sent the order id at booking) and update courier_status. Always returns
 * 200 quickly so the courier doesn't retry needlessly.
 *
 * Task T3: SPX gets its own branch (handleSpx()) rather than sharing the
 * generic path above, for two reasons SPX's docs make load-bearing (api.md
 * §4.1 + integration-brief.md §7):
 *  - Auth is a signed `check-sign` header (the same HMAC-SHA256 scheme as
 *    every outbound SPX call), NOT the shared-secret `?token=` query param
 *    every other courier here uses — SPX never sends that query param, and
 *    reusing that check would just always reject.
 *  - SPX imposes a hard 3-second response budget and PERMANENTLY stops
 *    sending webhooks after repeated slow/failed acks — so the actual
 *    order-matching + StatusMapper work must not block the response. See
 *    ProcessCourierWebhookJob's docblock for why `dispatch()->afterResponse()`
 *    (not this app's configured `database` queue connection) is the
 *    mechanism chosen.
 * Every other courier (QuadX) keeps the original inline, synchronous path —
 * unchanged in every observable way (same token check, same
 * CourierWebhookIngestor::ingest() call the code used to inline as a private
 * method) — its own tests are the contract for that.
 */
class CourierWebhookController extends Controller
{
    public function handle(Request $request, string $provider)
    {
        $courier = CourierManager::get($provider);
        if (!$courier) {
            return response()->json(['error' => 'unknown provider'], 404);
        }

        if ($provider === 'spx' && $courier instanceof SpxFulfillment) {
            return $this->handleSpx($request, $courier);
        }

        // Unguessable per-store token check (every non-SPX provider).
        $expected = Setting::where('group', 'courier')->where('key', "{$provider}_webhook_token")->value('value');
        if (!$expected || !hash_equals($expected, (string) $request->query('token'))) {
            return response()->json(['error' => 'unauthorized'], 403);
        }

        $events = $courier->parseWebhook($request->all());
        CourierWebhookIngestor::ingest($provider, $events);

        return response()->json(['ok' => true]);
    }

    /** How far the signed `timestamp` header of an SPX push may be from now, either way. */
    public const SPX_FRESH_SECONDS = 600;

    /** The most of an unverified body that is ever written to the log. */
    public const SPX_LOG_BYTES = 2048;

    /** A push larger than this is not one of SPX's events. */
    private const SPX_MAX_BYTES = 65536;

    /** The only fields of an unverified push worth logging: they identify the parcel, not a person. */
    private const SPX_LOGGED_FIELDS = ['tracking_no', 'order_id', 'customer_order_id', 'status_code', 'timestamp'];

    /**
     * SPX's signed, fast-ack branch. Always answers 200: SPX stops pushing
     * to an endpoint that keeps failing, and a rejected push is no reason to
     * lose every later one. A push that fails the signature, carries a
     * `timestamp` more than SPX_FRESH_SECONDS from now (a replay), or is too
     * large is logged, without personal data, and dropped. A verified push
     * is parsed now and applied after the response is sent.
     */
    private function handleSpx(Request $request, SpxFulfillment $courier)
    {
        $raw = $request->getContent();

        $problem = null;
        if (strlen($raw) > self::SPX_MAX_BYTES) {
            $problem = 'body too large';
        } elseif (!$courier->verifyWebhookSignature($request)) {
            $problem = 'signature mismatch';
        } else {
            $skew = abs(now()->getTimestamp() - (int) $request->header('timestamp'));
            if ($skew > self::SPX_FRESH_SECONDS) {
                $problem = "timestamp {$skew}s from now";
            }
        }

        if ($problem !== null) {
            Log::warning('SPX webhook rejected', [
                'reason' => $problem,
                'bytes'  => strlen($raw),
                'has_app_id' => $request->headers->has('app-id'),
                'fields' => self::loggableFields($raw),
            ]);

            return response()->json(['ok' => true]);
        }

        $payload = json_decode($raw, true);
        $events = is_array($payload) ? $courier->parseWebhook($payload) : [];

        if ($events !== []) {
            dispatch(new ProcessCourierWebhookJob('spx', $events))->afterResponse();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * The identifying fields of an unverified body, each shortened, the
     * whole capped at SPX_LOG_BYTES. Names, phones, addresses and photo
     * links are never kept.
     *
     * @return array<string, string>|string
     */
    private static function loggableFields(string $raw): array|string
    {
        $decoded = json_decode(substr($raw, 0, self::SPX_MAX_BYTES), true);
        if (!is_array($decoded)) {
            return 'not JSON';
        }

        $kept = [];
        foreach (self::SPX_LOGGED_FIELDS as $field) {
            if (isset($decoded[$field]) && is_scalar($decoded[$field])) {
                $kept[$field] = mb_substr((string) $decoded[$field], 0, 64);
            }
        }
        while ($kept !== [] && strlen((string) json_encode($kept)) > self::SPX_LOG_BYTES) {
            array_pop($kept);
        }

        return $kept;
    }
}
