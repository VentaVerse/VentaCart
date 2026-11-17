{{--
    What the address fields need to fill their lists, read once per page from
    the same core sources the store's other themes use: the address lookup
    providers (province, city, barangay lists per country), each country's
    phone code, and core's address lookup routes.
--}}
@php
    $addressCountries = \App\Models\Country::where('is_active', true)->get(['id', 'phone_code']);
    try {
        $addressProviders = app(\App\Services\AddressProviderRegistry::class)->toFrontendManifest();
    } catch (\Throwable $e) {
        report($e);
        $addressProviders = [];
    }
    $addressData = [
        'providers' => (object) $addressProviders,
        'phoneCodes' => (object) $addressCountries->pluck('phone_code', 'id')->filter()->all(),
        'zonesUrl' => route('storefront.address.zones'),
    ];
@endphp
<script type="application/json" id="xelga-address-data">@json($addressData)</script>
