{{-- One home, one tab bar (owner ruling 2026-08-19): operations and
     configuration side by side under the extension. --}}
<div class="v3-tabs" role="tablist" aria-label="Abandoned carts workspace">
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.index') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.index') }}">Carts</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.notifications') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.notifications') }}">Notifications</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.recovered') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.recovered') }}">Recovered</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.rules*') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.rules') }}">Rules</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.templates*') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.templates') }}">Templates</a>
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.abandoned-carts.settings') ? 'true' : 'false' }}" href="{{ route('admin.abandoned-carts.settings') }}">Settings</a>
</div>
