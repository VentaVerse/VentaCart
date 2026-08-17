{{-- The worklist's three views of one list, plus the one labeled
     crossing into configuration (never a foreign menu). --}}
<div class="v3-tabs" role="tablist" aria-label="Abandoned carts workspace">
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.index') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.index') }}">Carts</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.notifications') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.notifications') }}">Notifications</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.recovered') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.recovered') }}">Recovered</a>
    <a class="v3-tabs__aside" href="{{ route('admin.abandoned-carts.settings') }}">Settings <x-admin3.icon name="chevron" /></a>
</div>
