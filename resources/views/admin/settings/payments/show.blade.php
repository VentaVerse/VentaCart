<x-admin3.shell :title="$name">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.payment-settings.index') }}">Payments</a>
                <span aria-hidden="true">/</span>
                <span>{{ $name }}</span>
            </div>
            <h1 class="v3-head__title">{{ $name }}</h1>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch :name="$extension" />
        </div>
    </div>

    <div x-data="{ tab: @js(request('tab', 'config')),
            pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
        <div class="v3-modgrid">
        <nav class="v3-snav" aria-label="Sections">
            <button type="button" class="v3-snav__item" :aria-current="tab === 'config' ? 'page' : false" @click="pick('config')">
                <x-admin3.icon name="settings" />
                Configuration
            </button>
            <button type="button" class="v3-snav__item" :aria-current="tab === 'availability' ? 'page' : false" @click="pick('availability')">
                <x-admin3.icon name="globe" />
                Availability
            </button>
        </nav>
        <div class="v3-modmain">

        <div x-show="tab === 'config'" class="v3-settle">
            @include($configView)
        </div>

        <div x-show="tab === 'availability'" x-cloak class="v3-settle">
            @include('admin.settings.payments.partials.availability')
        </div>
        </div>
        </div>
    </div>
</x-admin3.shell>
