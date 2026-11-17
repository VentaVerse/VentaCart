{{--
    Country and the place fields of an address. Without the script every
    place is a text field; with it, a country that has an address lookup
    (provinces, cities, barangays) gets lists that fill one from another,
    and other countries get their regions as a list when core has them,
    exactly as core's own address form does. The field names are core's:
    country_id, state, city, district, barangay.
--}}
@php
    $named = $named ?? true;
    $places = [
        'state' => 'State or region',
        'city' => 'City',
        'district' => 'District',
        'barangay' => 'Barangay',
    ];
@endphp
<div class="addr-fields field--full" data-address-fields>
    <div class="field">
        <label class="field__label" for="{{ $prefix }}-country">Country</label>
        <select class="select" id="{{ $prefix }}-country" @if ($named) name="country_id" @endif data-addr="country_id" required autocomplete="country">
            <option value="">Choose a country</option>
            @foreach ($countries as $country)
                <option value="{{ $country->id }}" @selected((string) ($values['country_id'] ?? '') === (string) $country->id)>{{ $country->name }}</option>
            @endforeach
        </select>
    </div>
    @foreach ($places as $key => $label)
        <div class="field" data-addr-field="{{ $key }}" @if (in_array($key, ['district', 'barangay'], true) && empty($values[$key])) hidden @endif>
            <label class="field__label" for="{{ $prefix }}-{{ $key }}" data-addr-label>{{ $label }}</label>
            <select class="select" id="{{ $prefix }}-{{ $key }}-list" data-addr-select="{{ $key }}" @if ($named) name="{{ $key }}" @endif hidden disabled></select>
            <input class="input" id="{{ $prefix }}-{{ $key }}" type="text" data-addr-input="{{ $key }}" @if ($named) name="{{ $key }}" @endif value="{{ $values[$key] ?? '' }}" maxlength="255" @if ($key === 'city') required autocomplete="address-level2" @elseif ($key === 'state') autocomplete="address-level1" @endif>
        </div>
    @endforeach
</div>
