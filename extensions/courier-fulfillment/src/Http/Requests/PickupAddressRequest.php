<?php

namespace Extensions\CourierFulfillment\Http\Requests;

use Extensions\CourierFulfillment\Support\PhPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Validation for both create and edit of a pickup address.
 *
 * country_id is deliberately absent from the rules. The country is not a
 * question this form asks: it is resolved at runtime from
 * PickupCountry::ISO and stamped by the controller, so a forged country_id
 * in the request body is not rejected loudly, it simply never reaches the
 * model. Adding a rule for it would imply the field is negotiable.
 *
 * barangay is required even though config('localization.deep_address.PH')
 * marks the level optional for a shopper's address: SPX refuses a sender
 * block without one (SpxFulfillment::senderInfo()), so an address saved
 * without it would be a bookable-looking row that no booking can use.
 * district stays optional, because it exists for Manila only.
 */
class PickupAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('courier-fulfillment.configure');
    }

    public function rules(): array
    {
        return [
            'label'         => ['required', 'string', 'max:100'],
            'contact_name'  => ['required', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'address_1'     => ['required', 'string', 'max:500'],
            'state'         => ['required', 'string', 'max:150'],
            'city'          => ['required', 'string', 'max:150'],
            'district'      => ['nullable', 'string', 'max:150'],
            'barangay'      => ['required', 'string', 'max:150'],
            'postcode'      => ['required', 'string', 'max:32'],
            'is_default'    => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'address_1'     => 'address',
            'contact_phone' => 'mobile number',
            'state'         => 'province',
            'postcode'      => 'postal code',
        ];
    }

    /**
     * The mobile number is checked by the same rule the courier payload is
     * later built with (PhPhone::normalize, shared with SpxFulfillment), so
     * a number this form accepts can never be one SPX refuses at booking.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $phone = $this->input('contact_phone');

            if (is_string($phone) && $phone !== '' && PhPhone::normalize($phone) === null) {
                $validator->errors()->add(
                    'contact_phone',
                    'Enter a Philippine mobile number, for example 09171234567 or +639171234567.'
                );
            }
        });
    }
}
