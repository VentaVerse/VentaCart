{{--
    Add or edit an address: core's fields and checks (name, country and
    places, street, postcode, mobile number, default). The place lists come
    from core's address lookup; the page sends the same field names as
    core's own form.
--}}
@php
    app(\Themes\Xelga\Rendering\Storefront::class)->page('account');
    $who = auth('customer')->user();
    $values = [
        'country_id' => old('country_id', $address?->country_id),
        'state' => old('state', $address?->state),
        'city' => old('city', $address?->city),
        'district' => old('district', $address?->district),
        'barangay' => old('barangay', $address?->barangay),
    ];
    $title = $address ? 'Edit address' : 'Add an address';
@endphp
<x-store-layout :title="$title">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="address-form">
        <div class="s__inner">
            @include('xelga::partials.fixed.crumbs', ['trail' => [['label' => 'Your account', 'url' => route('account.dashboard')], ['label' => 'Addresses', 'url' => route('account.addresses')], ['label' => $title]]])
            <div class="acct">
                @include('xelga::partials.fixed.account-nav')
                <div class="acct__main">
                    <header class="acct__head"><h1 class="acct__title">{{ $title }}</h1></header>
                    @include('xelga::partials.fixed.flash', ['showErrors' => true])
                    <form class="acct__form acct__card" method="POST" action="{{ $address ? route('account.addresses.update', $address) : route('account.addresses.store') }}" data-address-form>
                        @csrf
                        @if ($address)
                            @method('PUT')
                        @endif
                        <div class="form-grid">
                            <div class="field">
                                <label class="field__label" for="ad-first">First name</label>
                                <input class="input" id="ad-first" type="text" name="first_name" value="{{ old('first_name', $address?->first_name ?? $who?->first_name) }}" autocomplete="given-name" maxlength="255" required>
                            </div>
                            <div class="field">
                                <label class="field__label" for="ad-last">Last name</label>
                                <input class="input" id="ad-last" type="text" name="last_name" value="{{ old('last_name', $address?->last_name ?? $who?->last_name) }}" autocomplete="family-name" maxlength="255" required>
                            </div>
                            @include('xelga::partials.fixed.address-fields', ['prefix' => 'ad', 'values' => $values, 'countries' => $countries])
                            <div class="field field--full">
                                <label class="field__label" for="ad-line">House number, street and building</label>
                                <input class="input" id="ad-line" type="text" name="address_1" value="{{ old('address_1', $address?->address_1) }}" autocomplete="address-line1" maxlength="1000" required>
                            </div>
                            <div class="field">
                                <label class="field__label" for="ad-zip">Postal code <span class="muted">(optional)</span></label>
                                <input class="input tabular" id="ad-zip" type="text" name="postcode" value="{{ old('postcode', $address?->postcode) }}" autocomplete="postal-code" maxlength="20">
                            </div>
                            <div class="field">
                                <label class="field__label" for="ad-phone">Mobile number</label>
                                <div class="phone">
                                    <span class="phone__code" data-phone-code aria-hidden="true"></span>
                                    <input class="input tabular" id="ad-phone" type="tel" name="phone" value="{{ old('phone', $address?->phone) }}" autocomplete="tel-national" inputmode="numeric" pattern="[0-9]*" minlength="7" maxlength="13" required data-digits-only>
                                </div>
                            </div>
                        </div>
                        <label class="check"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address?->is_default))><span>Make this my default address</span></label>
                        <div class="acct__form-acts">
                            <button type="submit" class="btn">{{ $address ? 'Save the address' : 'Add the address' }}</button>
                            <a class="btn btn--secondary" href="{{ route('account.addresses') }}">Cancel</a>
                        </div>
                    </form>
                    @include('xelga::partials.fixed.address-data')
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
