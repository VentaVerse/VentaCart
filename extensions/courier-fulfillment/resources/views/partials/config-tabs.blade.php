{{-- The configuration's own menu (owner ruling 2026-08-19, "exactly like
     Settings and SEO"): module settings, the mapping, pickup addresses,
     then one entry per registered courier - a new courier gets its tab
     for free. Expects an optional $activeCourier key. --}}
<div class="v3-tabs" role="tablist" aria-label="Fulfillment configuration">
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.fulfillment.settings') ? 'true' : 'false' }}" href="{{ route('admin.fulfillment.settings') }}">Settings</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.fulfillment.settings.placement') ? 'true' : 'false' }}" href="{{ route('admin.fulfillment.settings.placement') }}">Status Mapping</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.fulfillment.pickup-addresses.*') ? 'true' : 'false' }}" href="{{ route('admin.fulfillment.pickup-addresses.index') }}">Pickup Addresses</a>
    @foreach (\Extensions\CourierFulfillment\CourierManager::all() as $tabCourier)
        <a class="v3-tab" role="tab" aria-selected="{{ ($activeCourier ?? null) === $tabCourier->getKey() ? 'true' : 'false' }}" href="{{ route('admin.fulfillment.settings.courier', $tabCourier->getKey()) }}">{{ $tabCourier->getName() }}</a>
    @endforeach
</div>
