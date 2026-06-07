<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use Extensions\CourierFulfillment\CourierManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Generic courier webhook receiver: /webhooks/courier/{provider}?token=...
 *
 * The provider segment routes to that courier's parseWebhook(), which returns
 * normalized events. We match each event back to a Venta order by reference_id
 * (we sent the order id at booking) and update courier_status. Always returns
 * 200 quickly so the courier doesn't retry needlessly.
 */
class CourierWebhookController extends Controller
{
    public function handle(Request $request, string $provider)
    {
        // Unguessable per-store token check.
        $expected = Setting::where('group', 'courier')->where('key', "{$provider}_webhook_token")->value('value');
        if (!$expected || !hash_equals($expected, (string) $request->query('token'))) {
            return response()->json(['error' => 'unauthorized'], 403);
        }

        $courier = CourierManager::get($provider);
        if (!$courier) {
            return response()->json(['error' => 'unknown provider'], 404);
        }

        $events = $courier->parseWebhook($request->all());

        foreach ($events as $event) {
            $this->applyEvent($provider, $event);
        }

        return response()->json(['ok' => true]);
    }

    private function applyEvent(string $provider, array $event): void
    {
        // Match by reference_id (order id we sent at booking); fall back to tracking number.
        $order = null;
        if (!empty($event['reference_id'])) {
            $order = Order::find($event['reference_id']);
        }
        if (!$order && !empty($event['tracking_number'])) {
            $order = Order::where('courier_tracking_number', $event['tracking_number'])->first();
        }

        if (!$order) {
            Log::info('Courier webhook: no matching order', ['provider' => $provider, 'event' => $event]);
            return;
        }

        $order->forceFill(['courier_status' => $event['status']])->save();
    }
}
