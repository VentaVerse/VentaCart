{{-- The configuration side's three pages, plus the one labeled crossing
     back to the worklist. --}}
<div class="v3-tabs" role="tablist" aria-label="Abandoned cart settings">
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.settings') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.settings') }}">Settings</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.rules*') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.rules') }}">Rules</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.templates*') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.templates') }}">Templates</a>
    <a class="v3-tabs__aside" href="{{ route('admin.abandoned-carts.index') }}">Carts workspace <x-admin3.icon name="chevron" /></a>
</div>
