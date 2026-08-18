<x-admin.shell :title="$name">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.payment-settings.index') }}">Payments</a>
                <span aria-hidden="true">/</span>
                <span>{{ $name }}</span>
            </div>
            <h1 class="v-head__title">{{ $name }}</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch :name="$extension" />
        </div>
    </div>

    <div x-data="{ tab: @js(request('tab', 'config')),
            pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
        <div class="v-modgrid">
        <nav class="v-snav" aria-label="Sections">
            <button type="button" class="v-snav__item" :aria-current="tab === 'config' ? 'page' : false" @click="pick('config')">
                <x-admin.icon name="settings" />
                Configuration
            </button>
            <button type="button" class="v-snav__item" :aria-current="tab === 'availability' ? 'page' : false" @click="pick('availability')">
                <x-admin.icon name="globe" />
                Availability
            </button>
        </nav>
        <div class="v-modmain">

        <div x-show="tab === 'config'" class="v-settle">
            @include($configView)
        </div>

        <div x-show="tab === 'availability'" x-cloak class="v-settle">
            @include('admin.settings.payments.partials.availability')
        </div>
        </div>
        </div>
    </div>
</x-admin.shell>
