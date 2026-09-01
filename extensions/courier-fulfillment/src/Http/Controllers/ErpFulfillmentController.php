<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Api\LogsApiCalls;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;
use Extensions\CourierFulfillment\CourierManager;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\ServiceabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ERP-facing booking endpoint: ERP -> Venta -> courier.
 *
 * The ERP is the order hub but does NOT talk to couriers; Venta owns the courier
 * integration. The ERP POSTs here to trigger a booking for a Venta order, Venta
 * executes it against QuadX (via the shared FulfillmentService) and returns the
 * tracking number. Idempotent — a repeat call returns the existing booking.
 *
 * Auth + logging match the rest of the ERP API (erp.auth + LogsApiCalls).
 */
class ErpFulfillmentController extends Controller
{
    use LogsApiCalls;

    public function book(Request $request, int $id, FulfillmentService $service): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        // Everything the admin queue's own booking form takes, with the same
        // rules, so an ERP booking can say where the parcel leaves from and
        // in which slot. A field the ERP leaves out is left out here too:
        // each courier applies its own default (QuadX its configured service
        // and parcel), and who pays defaults to the courier's first option,
        // which for QuadX is cash on pickup. This used to fill next_day, box
        // and net_off for every courier, QuadX's words sent to SPX.
        $data = $request->validate([
            'courier'           => ['nullable', 'string', 'max:32'],
            'service'           => ['nullable', 'string', 'max:64'],
            'parcel'            => ['nullable', 'string', 'max:64'],
            'buyer_payment'     => ['nullable', 'in:cod,prepaid'],
            'shipping_payment'  => ['nullable', 'string', 'max:32'],
            'insure'            => ['sometimes', 'boolean'],
            'length_cm'         => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'width_cm'          => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'height_cm'         => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'weight_kg'         => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'pickup_slot'       => ['sometimes', 'nullable', 'string'],
            'pickup_address_id' => ['sometimes', 'nullable', 'integer', 'exists:courier_fulfillment_pickup_addresses,id'],
        ]);

        $opts = array_filter($data, fn ($v) => $v !== null && $v !== '');
        $opts['courier'] = (string) ($opts['courier'] ?? $this->defaultCourierKey());
        // Bookings are uninsured by default; the ERP opts in explicitly.
        $opts['insure'] = $request->boolean('insure');

        $courier = CourierManager::get($opts['courier']);
        if ($courier && empty($opts['shipping_payment'])) {
            $opts['shipping_payment'] = self::bookingDefaultsFor($courier)['shipping_payment'];
        }

        $res = $service->book($order, $opts);

        if (!($res['success'] ?? false)) {
            $status = ($res['code'] ?? '') === 'courier_unavailable' ? 409 : 422;
            return $this->logAndRespond($request, [
                'error' => $res['error'] ?? 'Booking failed',
                'code'  => $res['code'] ?? null,
            ], $status);
        }

        return $this->logAndRespond($request, [
            'success'         => true,
            'already_booked'  => $res['already_booked'] ?? false,
            'order_id'        => $order->id,
            'courier'         => $res['courier'] ?? null,
            'tracking_number' => $res['tracking_number'] ?? null,
            'status'          => $res['status'] ?? null,
        ]);
    }

    /**
     * Cancel this order's courier booking. The full state transition
     * (booking_history snapshot, courier-field reset, status back to
     * To Pack) is FulfillmentService::cancelBooking() — the same code
     * the admin queue runs, reported as JSON.
     */
    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $res = FulfillmentService::cancelBooking($order);

        if (!$res['success']) {
            return $this->logAndRespond($request, ['error' => $res['error']], 422);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'tracking_number' => $res['tracking'] ?? null,
            'order_status' => $res['order_status'] ?? null,
            'message' => $res['message'],
        ]);
    }

    /**
     * Live tracking pull. Refreshes Venta's own courier_status /
     * timeline / links on the way through (same shared path as the
     * admin's Track button), then hands the ERP the result.
     */
    public function tracking(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $res = FulfillmentService::refreshTracking($order);

        if (!$res['success']) {
            return $this->logAndRespond($request, ['error' => $res['error']], 422);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'courier' => $order->courier_provider,
            'tracking_number' => $order->courier_tracking_number,
            'status' => $res['status'],
            'timeline' => $res['timeline'],
            'links' => $res['links'],
        ]);
    }

    /**
     * Shipping label. When the courier's printLabel() cached the PDF
     * locally (SPX pattern — the provider link is perishable), the file
     * streams straight back on this authenticated call; otherwise the
     * provider's own presigned URL is returned for the ERP to fetch.
     */
    public function label(Request $request, int $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $courier = CourierManager::get((string) $order->courier_provider);
        if (!$courier || !$order->courier_tracking_number) {
            return $this->logAndRespond($request, ['error' => 'No courier booking to print'], 422);
        }

        $format = (string) $request->query('format', 'zebra');
        $result = $courier->printLabel($order->courier_tracking_number, $format);

        if (!($result['success'] ?? false) || empty($result['url'])) {
            return $this->logAndRespond($request, ['error' => 'Label not available: ' . ($result['error'] ?? 'no URL returned')], 422);
        }

        $cached = "courier-labels/{$order->courier_provider}/{$order->courier_tracking_number}.pdf";
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if ($disk->exists($cached)) {
            return $disk->download($cached, "label-order-{$order->id}.pdf");
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'url' => $result['url'],
        ]);
    }

    /**
     * Who will carry this parcel? Every configured courier's verdict at
     * once, same ServiceabilityService pass the booking modal uses
     * (bounded per-courier timeout; a refusal reads as "could not
     * check", never a hard error).
     */
    public function serviceability(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        if (FulfillmentService::bookingBlockedReason($order) !== null) {
            return $this->logAndRespond($request, ['success' => true, 'couriers' => [], 'all_refused' => false]);
        }

        $verdicts = ServiceabilityService::forOrder($order);

        // Each courier's answer carries its name, what a booking with it can
        // choose, and what it chooses by default, so the ERP's booking form
        // is this queue's own form and never has to know a courier itself.
        $couriers = [];
        foreach ($verdicts as $key => $verdict) {
            $courier = CourierManager::get((string) $key);
            $couriers[$key] = $verdict->toArray() + ($courier ? self::choicesFor($courier) : []);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'couriers' => $couriers,
            'all_refused' => ServiceabilityService::allRefused($verdicts),
        ]);
    }

    /**
     * Fee estimate for a booking the ERP is about to make. Quote-only,
     * creates nothing; same placement gate as booking itself.
     */
    public function estimate(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $blocked = FulfillmentService::bookingBlockedReason($order);
        if ($blocked !== null) {
            return $this->logAndRespond($request, ['error' => $blocked], 422);
        }

        $courierKey = (string) $request->input('courier', $this->defaultCourierKey());
        $courier = CourierManager::get($courierKey);
        if (!$courier || !$courier->isEnabled()) {
            return $this->logAndRespond($request, ['error' => 'Selected courier is not available'], 409);
        }

        $opts = collect($request->only([
            'service', 'parcel', 'shipping_payment', 'length_cm', 'width_cm',
            'height_cm', 'weight_kg', 'pickup_slot', 'pickup_address_id',
        ]))->filter(fn ($v) => $v !== null)->all();
        $opts['insure'] = $request->boolean('insure');

        $estimate = $courier->estimateFee($order, $opts);
        if (!$estimate) {
            return $this->logAndRespond($request, ['error' => 'An estimate is not available yet. Check the pickup address and this order\'s shipping address are complete.'], 422);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'courier' => $courierKey,
            // `amount` is the shipping fee, unchanged. Insurance is reported
            // beside it rather than folded into it, so an ERP reading this
            // field keeps getting the figure it has always got.
            'amount' => $estimate->amount,
            'currency' => $estimate->currency,
            'label' => $estimate->label ?? ($estimate->currency . ' ' . number_format($estimate->amount, 2)),
            'insurance' => $estimate->insurance ?? null,
            'total' => $estimate->total ?? $estimate->amount,
        ]);
    }

    /**
     * Pickup slots for a courier that offers them (SPX). Empty for
     * couriers without slot booking — same contract as the admin modal.
     */
    public function pickupSlots(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $courier = CourierManager::get((string) $request->query('courier', $this->defaultCourierKey() ?? ''));
        if (!$courier || !$courier->isEnabled()) {
            return $this->logAndRespond($request, ['error' => 'Selected courier is not available'], 409);
        }

        return $this->logAndRespond($request, [
            'success' => true,
            'order_id' => $order->id,
            'courier' => $courier->getKey(),
            'slots' => $courier->getPickupSlots($order),
        ]);
    }

    /**
     * Record a shipment with a courier this app does not integrate with:
     * the admin queue's "Record shipment", from the ERP. Same service path,
     * same refusals. order_status_id is optional here, unlike the admin
     * form: absent, the order moves to the module's booked status, which
     * is what the ERP expects and does not have the ids to say itself.
     */
    public function bookManual(Request $request, int $id, FulfillmentService $service): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $data = $request->validate([
            'shipping_courier_id' => ['nullable', 'integer'],
            'tracking_number'     => ['nullable', 'string', 'max:190'],
            'comment'             => ['nullable', 'string', 'max:500'],
            'notify_customer'     => ['sometimes', 'boolean'],
            'order_status_id'     => ['nullable', 'integer'],
        ]);

        // notify_customer is forwarded only when the ERP actually sent it.
        // It used to be coerced to a boolean either way, so an ERP that said
        // nothing was recorded as having said no, and the store's own notify
        // policy could never answer. Saying nothing now means "use the
        // policy"; sending it still overrides, either way.
        $opts = array_filter($data, fn ($v) => $v !== null && $v !== '');

        if ($request->has('notify_customer')) {
            $opts['notify_customer'] = $request->boolean('notify_customer');
        } else {
            unset($opts['notify_customer']);
        }

        $res = $service->bookManual($order, $opts);

        if (!($res['success'] ?? false)) {
            return $this->logAndRespond($request, [
                'error' => $res['error'] ?? 'Recording failed',
                'code'  => $res['code'] ?? null,
            ], 422);
        }

        return $this->logAndRespond($request, [
            'success'      => true,
            'order_id'     => $order->id,
            'courier'      => [
                'manual'          => true,
                'manual_courier'  => $res['courier'] ?? null,
                'tracking_number' => $res['tracking_number'] ?? null,
            ],
            'order_status' => $res['status'] ?? null,
        ]);
    }

    /** The undo of bookManual, from the ERP. */
    public function clearManual(Request $request, int $id, FulfillmentService $service): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            return $this->logAndRespond($request, ['error' => 'Order not found'], 404);
        }

        $res = $service->clearManual($order);

        if (!($res['success'] ?? false)) {
            return $this->logAndRespond($request, [
                'error' => $res['error'] ?? 'Clearing failed',
                'code'  => $res['code'] ?? null,
            ], 422);
        }

        return $this->logAndRespond($request, [
            'success'  => true,
            'order_id' => $order->id,
            'message'  => $res['message'] ?? 'Shipment cleared.',
            'order_status' => $res['status'] ?? null,
        ]);
    }

    /** Where a parcel can leave from, default first: the ERP's "Ships from". */
    public function pickupAddresses(Request $request): JsonResponse
    {
        $addresses = PickupAddress::query()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (PickupAddress $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'summary' => $a->summary(),
                'contact_name' => $a->contact_name,
                'contact_phone' => $a->contact_phone,
                'address_1' => $a->address_1,
                'barangay' => $a->barangay,
                'district' => $a->district,
                'city' => $a->city,
                'state' => $a->state,
                'postcode' => $a->postcode,
                'is_default' => (bool) $a->is_default,
            ]);

        return $this->logAndRespond($request, ['data' => $addresses]);
    }

    /**
     * What a booking with this courier can choose, and what it chooses when
     * nothing is chosen.
     *
     * @return array{name: string, service_types: array, parcel_options: array, shipping_payment_options: array, defaults: array{service: ?string, parcel: ?string, shipping_payment: ?string}}
     */
    private static function choicesFor(CourierFulfillmentInterface $courier): array
    {
        return [
            'name' => $courier->getName(),
            'service_types' => $courier->getServiceTypes(),
            'parcel_options' => $courier->getParcelOptions(),
            'shipping_payment_options' => $courier->getShippingPaymentOptions(),
            'defaults' => self::bookingDefaultsFor($courier),
        ];
    }

    /**
     * A courier's own defaults when it states them (QuadX does), otherwise
     * the first of each of its lists, which is what its select opens on.
     *
     * @return array{service: ?string, parcel: ?string, shipping_payment: ?string}
     */
    private static function bookingDefaultsFor(CourierFulfillmentInterface $courier): array
    {
        $first = fn (array $options) => $options !== [] ? (string) ($options[0]['value'] ?? '') : null;

        $defaults = [
            'service' => $first($courier->getServiceTypes()),
            'parcel' => $first($courier->getParcelOptions()),
            'shipping_payment' => $first($courier->getShippingPaymentOptions()),
        ];

        if (method_exists($courier, 'bookingDefaults')) {
            $defaults = array_merge($defaults, array_filter($courier->bookingDefaults(), fn ($v) => $v !== null && $v !== ''));
        }

        return $defaults;
    }

    private function defaultCourierKey(): ?string
    {
        $enabled = CourierManager::enabled();
        return $enabled ? $enabled[0]->getKey() : null;
    }
}
