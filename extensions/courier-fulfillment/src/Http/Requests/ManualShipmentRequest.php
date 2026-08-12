<?php

namespace Extensions\CourierFulfillment\Http\Requests;

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
            'shipping_courier_id' => [
                'required',
                'integer',
                Rule::exists(ShippingCourier::class, 'id')->where('is_active', true),
            ],
            'tracking_number' => ['nullable', 'string', 'max:190'],
            'note'            => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'shipping_courier_id' => 'courier',
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
