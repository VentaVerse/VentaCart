<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Extensions\CourierFulfillment\Jobs\ProcessCourierWebhookJob;
use Extensions\CourierFulfillment\Support\CourierWebhookIngestor;
use Illuminate\Http\Request;

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

    /**
     * SPX's signed, fast-ack branch. Rejects with 401 on a bad/missing
     * signature (never 403 — reserved for the shared-token couriers above,
     * so a log/monitoring split between "wrong secret" (401, signature
     * schemes) and "wrong token" (403, shared-secret schemes) stays clean).
     * On a valid signature: parse the events NOW (cheap, no I/O), hand them
     * to the queued job via `->afterResponse()`, and return 200 immediately
     * — the actual order-matching/StatusMapper work happens only after
     * Laravel has already flushed the HTTP response to SPX.
     */
    private function handleSpx(Request $request, SpxFulfillment $courier)
    {
        if (!$courier->verifyWebhookSignature($request)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $events = $courier->parseWebhook($request->all());

        dispatch(new ProcessCourierWebhookJob('spx', $events))->afterResponse();

        return response()->json(['ok' => true]);
    }
}
