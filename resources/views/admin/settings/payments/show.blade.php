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
        <div class="v-tabs" role="tablist">
            <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'config' ? 'true' : 'false'" @click="pick('config')">Configuration</button>
            <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'availability' ? 'true' : 'false'" @click="pick('availability')">Availability</button>
        </div>

        <div x-show="tab === 'config'" class="v-settle">
            @include($configView)
        </div>

        <div x-show="tab === 'availability'" x-cloak class="v-settle">
            @include('admin.settings.payments.partials.availability')
        </div>
    </div>
</x-admin.shell>
