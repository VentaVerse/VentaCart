{{-- One of the customer's addresses (core's CustomerAddress) for an extension's page, drawn as the account pages draw addresses. --}}
@include('xelga::partials.fixed.address', ['a' => [
    'first_name' => (string) $address->first_name,
    'last_name' => (string) $address->last_name,
    'address_1' => (string) $address->address_1,
    'barangay' => $address->barangay,
    'district' => $address->district,
    'city' => (string) $address->city,
    'state' => $address->state,
    'postcode' => $address->postcode,
    'country' => $address->country?->name,
    'phone' => $address->phone_display,
]])
