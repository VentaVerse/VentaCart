<?php

namespace Extensions\CourierFulfillment\Http\Requests;

use App\Models\OrderStatus;
use App\Models\ShippingCourier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording a parcel that leaves by a courier this app does not integrate
 * with (FulfillmentService::bookManual()).
 *
 * Three answers, one of them required. The courier must be one the store keeps
 * and still uses, so the rule reads `is_active` rather than merely `exists` —
 * a courier switched off is not on the picker, and a submission naming one is
 * either stale markup or forged.
 *
 * The tracking number is OPTIONAL on purpose, and that is the point of the
 * whole feature rather than a concession: the merchant books these by phone
 * and is routinely told the consignment number a day later, or never (the
 * customer collects at a branch). A required field here would push operators
 * into inventing one.
 *
 * Authorization is the route group's own: `auth` + `admin.session.cap`, the
 * same gate the integrated booking POST carries. Recording a shipment is no
 * more privileged than booking one, and inventing an asymmetric ability for
 * the cheaper of the two actions would only mean an admin who can book a real
 * parcel cannot write down that they posted one.
 */
class ManualShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional, and the select defaults to "Select courier..."
            // (card #135). This form mirrors "Update order" on the core order
            // page, so submitting with no courier is a plain status update:
            // the order moves, tracking and comment are saved, nobody is
            // recorded as carrying it. Requiring a courier meant an operator
            // in the packing queue had to open the order page to do something
            // as ordinary as moving a status.
            'shipping_courier_id' => [
                'nullable',
                'integer',
                Rule::exists(ShippingCourier::class, 'id')->where('is_active', true),
            ],
            'tracking_number' => ['nullable', 'string', 'max:190'],
            'comment'         => ['nullable', 'string', 'max:500'],
            'notify_customer' => ['sometimes', 'boolean'],
            // Which registered channels the packer left ticked. Names are
            // never validated against a list here: the service asks the
            // registry, so an unknown key is simply not matched.
            'channels'        => ['sometimes', 'array'],
            'channels.*'      => ['string', 'max:32'],
            // Owner directive (2026-08-12): "Add in manual booking the order
            // status change." Recording a manual shipment IS a status change
            // like any other, so the operator states which one rather than
            // having the module decide for them.
            //
            // Required since card #135, which mirrors this form on the core
            // order page's "Update order" panel. It used to be nullable, with
            // absence meaning the module's own To-Handover-placed status. That
            // default cannot survive courier becoming optional: a submission
            // with neither a courier nor a status would be a form that did
            // nothing while reporting success.
            //
            // Gated the same way the order page's own status select is:
            // every status belonging to this store, no invented subset.
            'order_status_id' => [
                'required',
                'integer',
                Rule::exists(OrderStatus::class, 'id')->where('store_id', (int) config('app.store_id', 1)),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'shipping_courier_id' => 'courier',
            'order_status_id'     => 'order status',
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_courier_id.required' => 'Choose the courier this parcel leaves by.',
            'shipping_courier_id.exists'   => 'That courier is not available. Choose an active one.',
        ];
    }
}
